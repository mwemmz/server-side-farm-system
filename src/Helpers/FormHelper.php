<?php

class FormHelper {
    public static function generateForm($fields, $action, $method = 'POST', $errors = []) {
        $formId = 'ff-form-' . substr(md5($action . '|' . $method), 0, 8);

        $html  = "<form id='$formId' action='" . htmlspecialchars($action) . "' method='" . htmlspecialchars($method) . "' class='space-y-6'>";

        if (!empty($errors['general'])) {
            $html .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm' role='alert'>" . htmlspecialchars($errors['general']) . "</div>";
        }

        foreach ($fields as $field) {
            $name  = $field['name'];
            $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
            $type  = $field['type'] ?? 'text';
            $value = $field['value'] ?? '';
            $required    = array_key_exists('required', $field) ? (bool) $field['required'] : true;
            $placeholder = $field['placeholder'] ?? '';
            $help        = $field['help'] ?? '';

            $border = isset($errors[$name]) ? 'border-red-400' : 'border-slate-200';
            $base = "class='w-full px-4 py-2.5 text-slate-800 bg-white/70 border {$border} rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:bg-white transition duration-150 placeholder:text-slate-400'";
            $req = $required ? ' required' : '';

            $html .= "<div class='flex flex-col gap-2'>";

            $labelHtml = htmlspecialchars($label);
            if ($required) {
                $labelHtml .= " <span class='text-red-500'>*</span>";
            } else {
                $labelHtml .= " <span class='text-slate-400 text-xs font-normal'>(optional)</span>";
            }
            $html .= "<label for='$name' class='text-sm font-semibold text-slate-700'>$labelHtml</label>";

            if ($type === 'textarea') {
                $html .= "<textarea name='$name' id='$name' rows='3' placeholder='" . htmlspecialchars($placeholder) . "'$req $base>" . htmlspecialchars($value) . "</textarea>";
            } elseif ($type === 'select') {
                $html .= "<select name='$name' id='$name'$req $base>";
                if ($placeholder !== '') {
                    $html .= "<option value=''>" . htmlspecialchars($placeholder) . "</option>";
                }
                foreach (($field['options'] ?? []) as $optVal => $optLabel) {
                    $sel = ((string) $optVal === (string) $value) ? ' selected' : '';
                    $html .= "<option value='" . htmlspecialchars((string) $optVal) . "'$sel>" . htmlspecialchars((string) $optLabel) . "</option>";
                }
                $html .= "</select>";
            } else {
                $attrs = '';
                if (isset($field['step'])) { $attrs .= " step='" . htmlspecialchars((string) $field['step']) . "'"; }
                if (isset($field['min']))  { $attrs .= " min='" . htmlspecialchars((string) $field['min']) . "'"; }
                if (isset($field['max']))  { $attrs .= " max='" . htmlspecialchars((string) $field['max']) . "'"; }
                $html .= "<input type='" . htmlspecialchars($type) . "' name='$name' id='$name' value='" . htmlspecialchars($value) . "' placeholder='" . htmlspecialchars($placeholder) . "' autocomplete='off'$attrs$req $base>";
            }

            if (isset($errors[$name])) {
                $html .= "<span class='text-red-600 text-xs font-medium'>" . htmlspecialchars($errors[$name]) . "</span>";
            }
            if ($help !== '') {
                $html .= "<span class='text-xs text-slate-400'>" . htmlspecialchars($help) . "</span>";
            }

            $html .= "</div>";
        }

        $html .= "<button type='submit' class='w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg shadow-green-900/30 transition duration-150 disabled:opacity-50 disabled:cursor-not-allowed'>Submit</button>";
        $html .= "</form>";
        $html .= "<script>(function(){var f=document.getElementById('$formId');if(!f)return;var b=f.querySelector('button[type=submit]');if(!b)return;function chk(){b.disabled=!f.checkValidity();}f.addEventListener('input',chk);f.addEventListener('change',chk);chk();})();</script>";

        return $html;
    }

    public static function options($pdo, $sql) {
        $options = [];
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $vals = array_values($row);
            $options[$vals[0]] = $vals[1] ?? $vals[0];
        }
        return $options;
    }

    public static function farmOptions($pdo) {
        return self::options($pdo, "SELECT id, name FROM farms ORDER BY name");
    }
}
