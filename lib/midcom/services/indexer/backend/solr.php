<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Solr implementation of the indexer backend.
 *
 * This works by communicating with solr over http requests.
 *
 * @see midcom_services_indexer
 * @package midcom.services
 */
class midcom_services_indexer_backend_solr implements midcom_services_indexer_backend
{
    /**
     * The "index" to use (Solr has single index but we add this as query constraint as necessary
     */
    private $_index_name;

    /**
     * The xml factory class
     *
     * @var midcom_services_indexer_solrDocumentFactory
     */
    private $factory;

    /**
     * Constructor is empty at this time.
     */
    public function __construct($index_name = null)
    {
        if ($index_name === null) {
            $this->_index_name = midcom::get()->config->get('indexer_index_name');
            if ($this->_index_name == 'auto') {
                $this->_index_name = midcom_connection::get_unique_host_name();
            }
        } else {
            $this->_index_name = $index_name;
        }
        $this->factory = new midcom_services_indexer_solrDocumentFactory($this->_index_name);
    }

    /**
     * Adds a document to the index.
     *
     * Any warning will be treated as error.
     *
     * Note, that $document may also be an array of documents without further
     * changes to this backend.
     *
     * @param midcom_services_indexer_document[] $documents A list of objects.
     * @return boolean Indicating success.
     */
    public function index(array $documents)
    {
        $this->factory->reset();

        $added = false;
        foreach ($documents as $document) {
            if (!$document->actually_index) {
                continue;
            }
            $this->factory->add($document);
            $added = true;
        }

        if (!$added) {
            return true;
        }

        $this->post();
    }

    /**
     * Removes the document(s) with the given resource identifier(s) from the index.
     *
     * @param array $RIs The resource identifier(s) of the document(s) that should be deleted.
     */
    public function delete(array $RIs)
    {
        $this->factory->reset();
        array_map([$this->factory, 'delete'], $RIs);
        $this->post();
    }

    /**
     * Clear the index completely or by constraint.
     */
    public function delete_all($constraint)
    {
        $this->factory->delete_all($constraint);
        $this->post(empty($constraint));
    }

    /**
     * Posts the xml to the suggested url using Buzz.
     */
    private function post($optimize = false)
    {
        $request = $this->prepare_request('update', $this->factory->to_xml())
            ->withMethod('POST');
        $this->send_request($request);

        $request = $this->prepare_request('update', ($optimize) ? '<optimize/>' : '<commit/>')
            ->withMethod('POST');
        $this->send_request($request);
    }

    /**
     * {@inheritDoc}
     */
    public function query($querystring, midcom_services_indexer_filter $filter = null, array $options = [])
    {
        // FIXME: adapt the whole indexer system to fetching enable querying for counts and slices
        $query = array_merge(midcom::get()->config->get('indexer_config_options'), $options);
        $query['q'] = $querystring;

        if (!empty($this->_index_name)) {
            $query['fq'] = '__INDEX_NAME:"' . rawurlencode($this->_index_name) . '"';
        }
        if ($filter !== null) {
            $query['fq'] = (isset($query['fq']) ? $query['fq'] . ' AND ' : '') . $filter->get_query_string();
        }

        $request = $this->prepare_request('select?' . http_build_query($query));
        $response = $this->send_request($request);
        if ($response === false) {
            return false;
        }

        $document = new DOMDocument;
        $document->loadXML((string) $response->getBody());
        $xquery = new DOMXPath($document);
        $result = [];

        $num = $xquery->query('/response/result')->item(0);
        if ($num->getAttribute('numFound') == 0) {
            return $result;
        }

        foreach ($xquery->query('/response/result/doc') as $res) {
            $doc = new midcom_services_indexer_document();
            foreach ($res->childNodes as $str) {
                $name = $str->getAttribute('name');

                $doc->add_result($name, ($str->tagName == 'float') ? (float) $str->nodeValue : (string) $str->nodeValue);
                if ($name == 'RI') {
                    $doc->add_result('__RI', $str->nodeValue);
                }
                if ($name == 'score') {
                    $doc->score = (float) $str->nodeValue;
                }
            }
            /* FIXME: before result slicing is properly supported this can be too heavy
            if (   isset($doc->source)
                && mgd_is_guid($doc->source))
            {
                midcom::get()->cache->content->register($doc->source);
            }
            */
            $result[] = $doc;
        }

        debug_add(sprintf('Returning %d results', count($result)), MIDCOM_LOG_INFO);
        return $result;
    }

    private function prepare_request($action, $body = null)
    {
        $uri = "http://" . midcom::get()->config->get('indexer_xmltcp_host');
        $uri .= ":" . midcom::get()->config->get('indexer_xmltcp_port');

        $uri .= '/solr/';
        if (midcom::get()->config->get('indexer_xmltcp_core')) {
            $uri .= midcom::get()->config->get('indexer_xmltcp_core') . '/';
        }
        $uri .= $action;

        return new Request('GET', $uri, [
            'Accept-Charset' => 'UTF-8',
            'Content-type' => 'text/xml; charset=utf-8',
            'Connection' => 'close'
        ], $body);
    }

    private function send_request(Request $request)
    {
        $client = new Client();
        $response = $client->send($request);

        $code = $response->getStatusCode();
        if ($code != 200) {
            debug_print_r('Request content:', (string) $request->getBody());
            throw new midcom_error((string) $response->getReasonPhrase(), $code);
        }
        return $response;
    }
}