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

    function initTelegramTokenPicker() {
        var picker = document.querySelector('.telegram-token-picker');
        if (!picker || picker.dataset.telegramTokenPickerReady === '1') {
            return;
        }

        var message = document.querySelector('textarea[name$="[message]"]');
        if (!message) {
            return;
        }

        picker.dataset.telegramTokenPickerReady = '1';
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

    document.addEventListener('DOMContentLoaded', initTelegramTokenPicker);
    document.addEventListener('mauticPageLoaded', initTelegramTokenPicker);
    setTimeout(initTelegramTokenPicker, 500);
})();
