(() => {
    // Styles of "Das Viertel" (layout.php): offered only when an issue is open in the editor.
    const styles = window.eintrikotNewsletterStyles || {};
    wp.domReady(() => {
        Object.entries(styles).forEach(([block, list]) => {
            Object.entries(list).forEach(([name, label]) => {
                wp.blocks.registerBlockStyle(block, { name, label });
            });
        });
        // Quotes have one look in the mail; the core variants would only confuse.
        ['plain', 'large'].forEach((name) => wp.blocks.unregisterBlockStyle('core/quote', name));
    });
})();
