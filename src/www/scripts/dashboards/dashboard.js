import modalInit    from './dashboard-modals.js';
import layoutInit   from './dashboard-layout.js';
import addWidgetInit from './dashboard-add-widget.js';
import dropdownInit from './dashboard-dropdowns.js';

document.addEventListener('DOMContentLoaded', function () {
    modalInit();
    layoutInit();
    dropdownInit();
    addWidgetInit();
});
