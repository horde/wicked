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
    var h2 = toc.querySelector('h2'),
        ol = toc.querySelector('ol');
    if (!ol) {
        return;
    }
    h2.style.cursor = 'pointer';
    h2.addEventListener('click', function() {
        ol.hidden = !ol.hidden;
    });
});
