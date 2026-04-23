(function () {
    function insertAtCursor(textarea, value) {
        var start = textarea.selectionStart || textarea.value.length;
        var end = textarea.selectionEnd || textarea.value.length;
        var prefix = textarea.value.substring(0, start);
        var suffix = textarea.value.substring(end);
        var spacer = prefix && !/\s$/.test(prefix) ? ' ' : '';

        textarea.value = prefix + spacer + value + suffix;
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + spacer.length + value.length;
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function closestFieldContainer(element) {
        if (!element) {
            return null;
        }

        return element.closest('.form-group, .choice-wrapper, .form-control-wrapper, .panel-body > div, .col-md-12, .col-xs-12') || element.parentElement;
    }

    function initMaxTokenPicker() {
        var picker = document.querySelector('.max-token-picker');
        var message = document.querySelector('textarea[name$="[message]"]');

        if (!picker || !message || picker.dataset.maxTokenPickerReady === '1') {
            return;
        }

        picker.dataset.maxTokenPickerReady = '1';
        picker.setAttribute('autocomplete', 'off');

        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
            var $picker = jQuery(picker);
            try {
                $picker.select2('destroy');
            } catch (e) {
            }
            $picker.select2({
                minimumResultsForSearch: Infinity,
                width: '100%'
            });
            $picker.on('change', function () {
                if (!picker.value) {
                    return;
                }
                insertAtCursor(message, picker.value);
                $picker.val('').trigger('change.select2');
            });
            return;
        }

        picker.addEventListener('change', function () {
            if (!picker.value) {
                return;
            }
            insertAtCursor(message, picker.value);
            picker.value = '';
        });
    }

    function initMaxBotSelectionVisibility() {
        var scope = document.querySelector('.max-send-scope');
        var botSelection = document.querySelector('.max-bot-selection');

        if (!scope || !botSelection || botSelection.dataset.maxBotVisibilityReady === '1') {
            return;
        }

        var container = closestFieldContainer(botSelection);
        if (!container) {
            return;
        }

        botSelection.dataset.maxBotVisibilityReady = '1';

        function syncVisibility() {
            container.style.display = scope.value === 'selected_only' ? '' : 'none';

            if (scope.value !== 'selected_only') {
                if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
                    jQuery(botSelection).val('').trigger('change.select2');
                } else {
                    botSelection.value = '';
                }
            }
        }

        scope.addEventListener('change', syncVisibility);
        syncVisibility();
    }

    document.addEventListener('DOMContentLoaded', initMaxTokenPicker);
    document.addEventListener('DOMContentLoaded', initMaxBotSelectionVisibility);
    document.addEventListener('mauticPageLoaded', initMaxTokenPicker);
    document.addEventListener('mauticPageLoaded', initMaxBotSelectionVisibility);
    setTimeout(initMaxTokenPicker, 500);
    setTimeout(initMaxBotSelectionVisibility, 500);
})();
