<?php

require_once __DIR__ . '/SessionHelper.php';

class FormHelper {
    /**
     * Render a form.
     *
     * Field definition keys:
     *   name, label, type (text|number|date|textarea|select|combo|hidden), value,
     *   options (value => label) OR optgroups ([ 'Group' => [value => label] ]),
     *   required (default true), placeholder, help, step/min/max, suffix, default,
     *   readonly, computed,
     *   combo extras: allow_custom (bool), category (lookup category),
     *                 depends_on (parent field name), empty_label.
     */
    public static function generateForm($fields, $action, $method = 'POST', $errors = []) {
        $formId = 'ff-form-' . substr(md5($action . '|' . $method), 0, 8);

        $html  = "<form id='$formId' action='" . htmlspecialchars($action) . "' method='" . htmlspecialchars($method) . "' class='space-y-6'>";

        // A1: every POST form carries the session CSRF token.
        if (strcasecmp($method, 'POST') === 0) {
            $html .= "<input type='hidden' name='csrf_token' value='" . htmlspecialchars(SessionHelper::csrfToken()) . "'>";
        }

        if (!empty($errors['general'])) {
            $html .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm' role='alert'>" . htmlspecialchars($errors['general']) . "</div>";
        }

        $needsComboJs = false;

        foreach ($fields as $field) {
            $name  = $field['name'];
            $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
            $type  = $field['type'] ?? 'text';
            $value = $field['value'] ?? '';
            if (!is_scalar($value)) $value = '';
            if ($value === '' && isset($field['default'])) $value = $field['default'];
            $required    = array_key_exists('required', $field) ? (bool) $field['required'] : true;
            $placeholder = $field['placeholder'] ?? '';
            $help        = $field['help'] ?? '';
            $suffix      = $field['suffix'] ?? '';
            $readonly    = !empty($field['readonly']);

            $border = isset($errors[$name]) ? 'border-red-400' : 'border-slate-200';
            $base = "class='w-full px-4 py-2.5 text-slate-800 bg-white/70 border {$border} rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:bg-white transition duration-150 placeholder:text-slate-400'";
            $req = $required ? ' required' : '';
            $ro  = $readonly ? ' readonly' : '';

            $html .= "<div class='flex flex-col gap-2'>";

            $labelHtml = htmlspecialchars($label);
            if ($required) {
                $labelHtml .= " <span class='text-red-500'>*</span>";
            } else {
                $labelHtml .= " <span class='text-slate-400 text-xs font-normal'>(optional)</span>";
            }
            $html .= "<label for='$name' class='text-sm font-semibold text-slate-700'>$labelHtml</label>";

            if ($type === 'hidden') {
                $html .= "<input type='hidden' name='$name' id='$name' value='" . htmlspecialchars($value) . "'>";
                $html .= "</div>";
                continue;
            }

            if ($type === 'textarea') {
                $html .= "<textarea name='$name' id='$name' rows='3' placeholder='" . htmlspecialchars($placeholder) . "'$req $base>" . htmlspecialchars($value) . "</textarea>";
            } elseif ($type === 'select') {
                $selectOptions = self::normalizeOptions($field['options'] ?? []);
                $html .= "<select name='$name' id='$name'$req $base>";
                if ($placeholder !== '') {
                    $html .= "<option value=''>" . htmlspecialchars($placeholder) . "</option>";
                }
                if (!empty($field['optgroups'])) {
                    foreach ($field['optgroups'] as $group => $opts) {
                        $html .= "<optgroup label='" . htmlspecialchars((string) $group) . "'>";
                        foreach (self::normalizeOptions($opts) as $optVal => $optLabel) {
                            $sel = ((string) $optVal === (string) $value) ? ' selected' : '';
                            $html .= "<option value='" . htmlspecialchars((string) $optVal) . "'$sel>" . htmlspecialchars((string) $optLabel) . "</option>";
                        }
                        $html .= "</optgroup>";
                    }
                } else {
                    foreach ($selectOptions as $optVal => $optLabel) {
                        $sel = ((string) $optVal === (string) $value) ? ' selected' : '';
                        $html .= "<option value='" . htmlspecialchars((string) $optVal) . "'$sel>" . htmlspecialchars((string) $optLabel) . "</option>";
                    }
                }
                $html .= "</select>";
            } elseif ($type === 'combo') {
                $needsComboJs = true;
                $html .= self::renderCombo($field, $value, $required, $border);
            } else {
                $attrs = '';
                if (isset($field['step'])) { $attrs .= " step='" . htmlspecialchars((string) $field['step']) . "'"; }
                if (isset($field['min']))  { $attrs .= " min='" . htmlspecialchars((string) $field['min']) . "'"; }
                if (isset($field['max']))  { $attrs .= " max='" . htmlspecialchars((string) $field['max']) . "'"; }
                $html .= "<input type='" . htmlspecialchars($type) . "' name='$name' id='$name' value='" . htmlspecialchars($value) . "' placeholder='" . htmlspecialchars($placeholder) . "' autocomplete='off'$attrs$req$ro $base>";
            }

            if ($suffix !== '') {
                $html .= "<span class='text-xs text-slate-400'>Measured in: <strong>" . htmlspecialchars($suffix) . "</strong></span>";
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

        if ($needsComboJs && !self::$comboJsIncluded) {
            self::$comboJsIncluded = true;
            $html .= "<script src='views/js/lookup-combobox.js' defer></script>";
        }

        return $html;
    }

    private static $comboJsIncluded = false;

    /**
     * Normalise $options into a scalar value => label map. Tolerates the
     * legacy "list of ['value'=>,'label'=>]" shape and silently drops any
     * non-scalar entries so an "Array to string conversion" warning can never
     * be emitted while rendering options.
     */
    private static function normalizeOptions($options) {
        $out = [];
        foreach ((array) $options as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists('value', $value)) {
                    $k = (string) $value['value'];
                    $out[$k] = array_key_exists('label', $value) ? $value['label'] : $k;
                }
                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /** Build a pick-or-type combobox (text + hidden value + datalist + no-JS select). */
    private static function renderCombo($field, $value, $required, $border) {
        $name       = $field['name'];
        $options    = self::normalizeOptions($field['options'] ?? []);
        $allowCustom = !empty($field['allow_custom']);
        $category   = $field['category'] ?? '';
        $dependsOn  = $field['depends_on'] ?? '';
        $placeholder = $field['placeholder'] ?? 'Start typing to search…';
        $req = $required ? ' required' : '';

        // Display text: the label when the value matches an option, else the raw value.
        $display = $value;
        foreach ($options as $optVal => $optLabel) {
            if ((string) $optVal === (string) $value) { $display = $optLabel; break; }
        }

        $datalist = "<datalist id='{$name}_opts'>";
        $noJs = "<select name='$name' id='{$name}_noscript' class='" . htmlspecialchars($border) . " w-full px-4 py-2.5 rounded-lg border bg-white/70'>";
        $noJs .= "<option value=''>" . htmlspecialchars($placeholder) . "</option>";
        foreach ($options as $optVal => $optLabel) {
            $datalist .= "<option value='" . htmlspecialchars((string) $optLabel) . "' data-value='" . htmlspecialchars((string) $optVal) . "'></option>";
            $sel = ((string) $optVal === (string) $value) ? ' selected' : '';
            $noJs .= "<option value='" . htmlspecialchars((string) $optVal) . "'$sel>" . htmlspecialchars((string) $optLabel) . "</option>";
        }
        $datalist .= "</datalist>";
        $noJs .= "</select>";

        $dataAttrs  = " data-category='" . htmlspecialchars($category) . "'";
        $dataAttrs .= " data-allow-custom='" . ($allowCustom ? '1' : '0') . "'";
        $dataAttrs .= " data-name='" . htmlspecialchars($name) . "'";
        if ($dependsOn !== '') $dataAttrs .= " data-depends-on='" . htmlspecialchars($dependsOn) . "'";

        $base = "w-full px-4 py-2.5 text-slate-800 bg-white/70 border " . htmlspecialchars($border) . " rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:bg-white transition duration-150 placeholder:text-slate-400";

        return "<div class='ff-combo relative'$dataAttrs>"
            . "<input type='text' id='{$name}_input' class='ff-combo-input $base' list='{$name}_opts' value='" . htmlspecialchars((string) $display) . "' placeholder='" . htmlspecialchars($placeholder) . "' autocomplete='off'$req>"
            . "<input type='hidden' name='$name' id='$name' value='" . htmlspecialchars((string) $value) . "'>"
            . $datalist
            . "<noscript>$noJs</noscript>"
            . "</div>";
    }

    public static function options($pdo, $sql) {
        $options = [];
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $vals = array_values($row);
            $options[$vals[0]] = $vals[1] ?? $vals[0];
        }
        return $options;
    }

    /** value => "id — label" for record pickers. */
    public static function recordOptions($pdo, $sql) {
        $options = [];
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $vals = array_values($row);
            $id = $vals[0];
            $label = isset($vals[1]) ? $vals[1] : '';
            $extra = isset($vals[2]) && $vals[2] !== null ? ' — ' . $vals[2] : '';
            $options[$id] = $id . ' — ' . $label . $extra;
        }
        return $options;
    }

    public static function farmOptions($pdo) {
        return self::recordOptions($pdo, "SELECT id, name, location FROM farms ORDER BY name");
    }

    public static function cropOptions($pdo) {
        return self::recordOptions($pdo, "SELECT id, name FROM crops ORDER BY name");
    }

    public static function supplierOptions($pdo) {
        return self::recordOptions($pdo, "SELECT id, name FROM suppliers ORDER BY name");
    }

    public static function fieldOptions($pdo) {
        return self::recordOptions($pdo, "SELECT id, name FROM fields ORDER BY name");
    }

    public static function livestockOptions($pdo) {
        $options = [];
        foreach ($pdo->query("SELECT id, type, breed, tag_no, name FROM livestock WHERE COALESCE(status,'active') = 'active' ORDER BY type, tag_no NULLS LAST, id")->fetchAll() as $a) {
            $parts = [trim((string) $a['tag_no']) !== '' ? $a['tag_no'] : ucfirst((string) $a['type']) . ' #' . $a['id']];
            if (!empty($a['breed'])) $parts[] = $a['breed'];
            if (!empty($a['name'])) $parts[] = $a['name'];
            $options[(string) $a['id']] = implode(' · ', $parts);
        }
        return $options;
    }

    /** LookupHelper-backed options for a category. */
    public static function lookupOptions($pdo, $category) {
        require_once __DIR__ . '/LookupHelper.php';
        return LookupHelper::options($pdo, $category);
    }
}
?>
