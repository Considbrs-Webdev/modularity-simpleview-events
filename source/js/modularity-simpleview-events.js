/**
 * When a Simpleview events module is hidden because it has no events, the
 * container block it sits in (e.g. a coloured band) is left behind. Hide that
 * container too if it only holds spacers and empty event modules.
 */
const EVENTS_MODULE = '.modularity-mod-sv-events';
const SPACER_MODULE = '.modularity-mod-spacer';

const isEmptyEventsModule = (element) => element.matches(EVENTS_MODULE) && element.children.length === 0;

const hideEmptyContainers = () => {
    document.querySelectorAll(EVENTS_MODULE).forEach((module) => {
        if (!isEmptyEventsModule(module)) {
            return;
        }

        const container = module.closest('.t-block-container');
        const inner = container?.querySelector('.acf-innerblocks-container');

        if (!container || !inner) {
            return;
        }

        const onlySpacersAndEmptyModules = Array.from(inner.children).every(
            (child) => child.matches(SPACER_MODULE) || isEmptyEventsModule(child)
        );

        if (onlySpacersAndEmptyModules) {
            container.hidden = true;
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hideEmptyContainers);
} else {
    hideEmptyContainers();
}
