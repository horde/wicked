/**
 * Javascript code for making the TOC collapsible.
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

document.addEventListener('DOMContentLoaded', function() {
    var toc = document.getElementById('toc');
    if (!toc) {
        return;
    }
    var heading = toc.querySelector('h2');
    var list = toc.querySelector('ol');
    if (!heading || !list) {
        return;
    }
    heading.style.cursor = 'pointer';
    heading.addEventListener('click', function() {
        list.hidden = !list.hidden;
    });
});
