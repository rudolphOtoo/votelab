export default function defineReactiveMagicProperty(name, rawObject) {
    const instance = Alpine.reactive(rawObject);

    /** reactive objects are plain proxies and does not support hooks like stores,
     or scopes in alpine so we init manualy */
    if (typeof instance.init === 'function') {
        instance.init();
    }

    Alpine.magic(name, () => instance);
    // ex : if the magic called $modal we register Modal into the window
    window[name[0].toUpperCase() + name.slice(1)] = instance;
}
