<?php
$prefix = $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
$task =& $data['task'];
$action = 'complete';
$checked = '';

//Set rejected etc status classes
switch($task->status)
{
    case org_openpsa_projects_task_status_dba::REJECTED:
        $status_class = 'org_openpsa_status_rejected';
        break;
    default:
        $status_class = '';
        break;
}
//TODO: Check deliverables
//NOTE: The hidden input is there on purpose, if we remove a check from checkbox, it will not get posted at all...
?>
    <tr class="&(data['class']);">
      <td class="multivalue">
        <form method="post" action="<?php echo $prefix; ?>workflow/<?php echo $task->guid; ?>/">
        <input type="hidden" name="org_openpsa_projects_workflow_action[&(action);]" value="true" />
        <input type="checkbox"&(checked:h); name="org_openpsa_projects_workflow_dummy" value="true" onchange="this.form.submit()" class="completion" /><a class="&(status_class);" href="<?php echo $prefix; ?>task/<?php echo $task->guid; ?>/"><?php echo $task->title; ?></a>
        </form>
        </td>
        <td>
        <?php
        if ($data['view'] == 'project_tasks')
        {
            echo strftime('%x', $task->start) . ' - ' . strftime('%x', $task->end) . "\n";
        }
        else if ($parent = $task->get_parent())
        {
            if (is_a($parent, 'org_openpsa_projects_project'))
            {
                $parent_url = "{$prefix}project/{$parent->guid}/";
            }
            else
            {
                $parent_url = "{$prefix}task/{$parent->guid}/";
            }
            echo " <a href=\"{$parent_url}\">{$parent->title}</a>";
        }
        ?>
      </td>
      <td>
      <?php
          if(isset($data['priority_array']) && array_key_exists($task->priority, $data['priority_array']))
          {
              echo $data['l10n']->get($data['priority_array'][$task->priority]);
          }
      ?>
      </td>
    <td class="numeric">
      <span title="<?php echo $data['l10n']->get('planned hours'); ?>"><?php echo round($task->plannedHours, 2);
    ?>
    </span>
    </td>
    <td class="numeric">
      <span title="<?php echo $data['l10n']->get('reported'); ?>"><?php echo round($task->reportedHours, 2);
    ?>
    </span>
    </td>
    <td class="numeric">
      <span title="<?php echo $data['l10n']->get('invoiceable'); ?>"><?php echo round($task->invoiceableHours, 2);
    ?>
    </span>
    </td>
    </tr>