<?php
/**
 * @file
 * Provides Default Enum Key feature.
 */

require_once 'helper.php';

/**
 * Handles Default Enum Key functionality.
 *
 * The purpose is to force REDCap to retrieve keys instead labels when piping a
 * choice selection field over a @DEFAULT action tag.
 */
function auto_populate_fields_default_enum_key() {
    global $Proj, $user_rights, $double_data_entry;

    $aux_metadata = $Proj->metadata;
    $entry_num = ($double_data_entry && $user_rights['double_data'] != 0) ? '--' . $user_rights['double_data'] : '';

    // Temporarily overriding project metadata.
    foreach ($Proj->metadata as $field_name => $field_info) {
        // Overriding choice selection fields - checkboxes, radios and dropdowns.
        if (!in_array($field_info['element_type'], array('checkbox', 'radio', 'select'))) {
            continue;
        }

        if (!$options = parseEnum($Proj->metadata[$field_name]['element_enum'])) {
            continue;
        }

        foreach (array_keys($options) as $key) {
            // Replacing selection choices labels with keys.
            $options[$key] = $key . ',' . $key;
        }

        $Proj->metadata[$field_name]['element_enum'] = implode('\\n', $options);
    }

    foreach (auto_populate_fields_get_fields_names() as $target_field_name) {
        $misc = $Proj->metadata[$target_field_name]['misc'];

        // Getting multiple @DEFAULT action tags (e.g. @DEFAULT_1).
        $action_tags = auto_populate_fields_get_default_action_tags($misc);

        foreach ($action_tags as $action_tag) {
            $default_value = Form::getValueInQuotesActionTag($misc, $action_tag);
            if (empty($default_value) && !is_numeric($default_value)) {
                continue;
            }

            // Steping ahead REDCap and piping strings in our way.
            $default_value = Piping::replaceVariablesInLabel($default_value, $_GET['id'] . $entry_num, $_GET['event_id'], $_GET['instance'], array(), false, null, false);
            if (empty($default_value) && !is_numeric($default_value)) {
                continue;
            }

            // Applying value into the action tag.
            $aux_metadata[$target_field_name]['misc'] = auto_populate_fields_override_action_tag('@DEFAULT', $default_value, $misc);
            break;
        }
    }

    // Now that pipings are done, let's restore original project metadata.
    $Proj->metadata = $aux_metadata;
}
