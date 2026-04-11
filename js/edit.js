var WickedEdit = {
    loadPreview: function()
    {
        var f = document.getElementById('wicked-edit'), oldAction = f.action;

        f.action = 'preview.php';
        f.target = '_blank';
        f.submit();
        f.action = oldAction;
        f.target = '';
    },

    onDomLoad: function()
    {
        var btn = document.getElementById('wicked-preview');
        if (btn) {
            btn.addEventListener('click', this.loadPreview);
        }
    }
};

document.addEventListener('DOMContentLoaded', WickedEdit.onDomLoad.bind(WickedEdit));
