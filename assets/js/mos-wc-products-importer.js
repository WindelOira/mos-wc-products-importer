const app = new Vue({
    el          : '#mos-wc-app',
    vuetify     : new Vuetify(),
    components  : {
        'mos-app'           : httpVueLoader(mosWC.paths.pluginURL +'assets/js/components/App.vue')
    }
})