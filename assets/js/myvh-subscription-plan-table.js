(function (blocks, element) {
    var registerBlockType = blocks.registerBlockType;
    var createElement = element.createElement;

    registerBlockType('myvh/subscription-plan-table', {
        title: 'MYVH Subscription Plans',
        category: 'widgets',
        icon: 'table-col-after',
        edit: function () {
            return createElement(
                'div',
                { style: { padding: '16px', border: '1px dashed #b7c2cc' } },
                'MYVH subscription plan table renders on the frontend.'
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element);
