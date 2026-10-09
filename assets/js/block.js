wp.blocks.registerBlockType('bv-map/map-block', {
    title: wp.i18n.__('Map Block'),
    description: wp.i18n.__('This block calls a map where the stories feature images will be shown'),
    icon: 'location-alt',
    category: 'widgets',

    edit: function() {
        return wp.element.createElement('div', {
            id: 'map'
        }, 'Map placeholder');
    },
    save: function() {
        return wp.element.createElement('div', {
            id: 'map',
            className: 'is-style-wide'
        }, '');
    }
})
