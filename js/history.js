var WickedHistory = {
    onClick: function(e)
    {
        var elm = e.target.closest('input[type="submit"]');
        if (elm) {
            var form = document.getElementById('wicked-diff');
            var radios = form.querySelectorAll('input[type="radio"][name="v1"]');
            var value = Array.from(radios).find(function(radio) { return radio.checked; });
            if (elm.value == value.value) {
                e.preventDefault();
                e.stopPropagation();
            }
            document.getElementById('wicked-diff-v2').value = elm.value;
        }
    }
};

var wickedDiffForm = document.getElementById('wicked-diff');
if (wickedDiffForm) {
    wickedDiffForm.addEventListener('click', WickedHistory.onClick);
}
