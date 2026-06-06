(function (blocks, element) {
    var registerBlockType = blocks.registerBlockType;
    var createElement = element.createElement;

    registerBlockType('myvh/calendar', {
        title: 'MYVH Calendar',
        category: 'widgets',
        icon: 'calendar-alt',
        edit: function () {
            return createElement(
                'div',
                { style: { padding: '16px', border: '1px dashed #b7c2cc' } },
                'MYVH customer calendar renders on the frontend.'
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element);
