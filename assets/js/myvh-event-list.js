(function (blocks, element) {
    var registerBlockType = blocks.registerBlockType;
    var createElement = element.createElement;

    registerBlockType('myvh/event-list', {
        title: 'MYVH Event List',
        category: 'widgets',
        icon: 'list-view',
        edit: function () {
            return createElement(
                'div',
                { style: { padding: '16px', border: '1px dashed #b7c2cc' } },
                'MYVH event list renders on the frontend.'
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element);
