<template>
    <div>
        <v-card>
            <v-tabs 
                v-model="models.tabs"
                background-color="accent-4"
                center-active
                dark>
                <v-tab>
                    <v-icon class="mr-2">mdi-settings</v-icon> General Settings
                </v-tab>
                <v-tab>
                    <v-icon class="mr-2">mdi-import</v-icon> Import Products
                </v-tab>
                <v-tab>
                    <v-icon class="mr-2">mdi-folder-multiple-image</v-icon> Product Images
                </v-tab>
            </v-tabs>
        </v-card>

        <v-tabs-items 
            v-model="models.tabs">
            <v-tab-item>
                <v-card flat>
                    <v-card-text>
                        <mos-general-settings></mos-general-settings>
                    </v-card-text>
                </v-card>
            </v-tab-item>
            <v-tab-item v-if="settings.configured">
                <v-card flat>
                    <v-card-text>
                        <mos-import></mos-import>
                    </v-card-text>
                </v-card>
            </v-tab-item>
            <v-tab-item>
                <v-card flat>
                    <v-card-text>
                        <mos-product-images></mos-product-images>
                    </v-card-text>
                </v-card>
            </v-tab-item>
        </v-tabs-items>
    </div>
</template>

<script>
    module.exports = {
        components  : {
            'mos-general-settings'  : httpVueLoader(mosWC.paths.pluginURL +'assets/js/components/views/GeneralSettings.vue'),
            'mos-import'            : httpVueLoader(mosWC.paths.pluginURL +'assets/js/components/views/Import.vue'),
            'mos-product-images'    : httpVueLoader(mosWC.paths.pluginURL +'assets/js/components/views/ProductImages.vue')
        },
        data() {
            return {
                settings    : {
                    configured  : true
                },
                models      : {
                    tabs        : 0
                }
            }
        },
        mounted() {
            this.models.tabs = this.settings.configured ? 1 : this.settings.configured
        }
    }
</script>