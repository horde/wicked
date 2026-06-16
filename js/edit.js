var WickedEdit = {
    loadPreview: function () {
        var f = document.getElementById('wicked-edit'),
            btn = document.getElementById('wicked-preview'),
            oldAction = f.action,
            previewUrl = btn && btn.dataset.previewUrl;

        if (!previewUrl) {
            return;
        }

        f.action = previewUrl;
        f.target = '_blank';
        f.submit();
        f.action = oldAction;
        f.target = '';
    },

    onDomLoad: function () {
        var btn = document.getElementById('wicked-preview');
        if (btn) {
            btn.addEventListener('click', this.loadPreview);
        }
    }
};

document.addEventListener('DOMContentLoaded', WickedEdit.onDomLoad.bind(WickedEdit));
