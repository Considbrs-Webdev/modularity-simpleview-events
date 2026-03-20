import { createViteConfig } from "vite-config-factory";

const entries = {
    "css/modularity-simpleview-events": "./source/sass/modularity-simpleview-events.scss",
};

export default createViteConfig(entries, {
    outDir: "assets/dist",
    manifestFile: "manifest.json",
});
