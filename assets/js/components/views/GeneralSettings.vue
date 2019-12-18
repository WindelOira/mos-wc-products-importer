<template>
    <div>
        <v-alert 
            v-for="(error, key) in alerts.errors" 
            :key="'error-'+ key" 
            dismissible 
            type="error">{{ error.text }}</v-alert>

        <v-alert 
            v-for="(success, key) in alerts.success" 
            :key="'success-'+ key" 
            dismissible 
            type="success">{{ success.text }}</v-alert>

        <v-card>
            <v-progress-linear
                v-if="loading" 
                indeterminate
                color="green" 
                class="mb-4"></v-progress-linear>
            <v-card-title>Product Images Settings</v-card-title>
            <v-card-text>
                <v-text-field 
                    v-model="delimiter" 
                    label="Supplier Part Number delimiters" 
                    hint="Set delimiter characters for 'Supplier Part Number' used for matching product images.">
                    <v-icon 
                        @click="addDelimiter(delimiter)"
                        slot="append" 
                        color="primary">mdi-plus</v-icon>
                </v-text-field>
                <v-chip v-for="(delimiter, key) in models.images.delimiters" 
                        :key="'delimiter-'+ key" 
                        @click:close="removeDelimiter(delimiter)" 
                        pill 
                        small 
                        close 
                        class="mt-1 mr-1">{{ delimiter }}</v-chip>
                <v-switch v-model="models.images.findFirst" 
                    label="Find the first part of 'Supplier Part Number' only"></v-switch>
            </v-card-text>
        </v-card>

        <v-btn 
            @click="saveSettings"
            color="primary" 
            class="mt-4">Save Settings</v-btn>
    </div>
</template>

<script>
    module.exports = {
        data() {
            return {
                alerts      : {
                    success     : [],
                    errors      : []
                },
                settings    : {
                    nonce       : mosWC.ajax.nonce,
                    actions     : {
                        post        : mosWC.ajax.url
                    }
                },
                models  : {
                    images  : {
                        findFirst   : true,
                        delimiters  : []
                    }
                },
                loading     : false,
                delimiter   : ''
            }
        },
        methods : {
            addDelimiter(delimiter) {
                if( 0 > this.models.images.delimiters.indexOf(delimiter) ) {
                    this.models.images.delimiters.push(delimiter)
                }
                this.delimiter = ''
            },
            removeDelimiter(delimiter) {
                this.models.images.delimiters.splice(this.models.images.delimiters.indexOf(delimiter), 1)
            },
            getSettings() {
                this.loading = true

                axios({
                    url     : this.settings.actions.post,
                    method  : 'POST',
                    data    : Qs.stringify({
                        nonce       : this.settings.nonce,
                        action      : 'ajaxGetSettings',
                        options     : {
                            images      : ['delimiters']
                        }
                    })
                }).then(response => {
                    this.loading = false
                    this.models.images.delimiters = response.data.images.delimiters != 'false' ? response.data.images.delimiters : []
                }).catch(error => {
                    console.log(error)
                })
            },
            saveSettings() {
                this.loading = true
                
                axios({
                    url     : this.settings.actions.post,
                    method  : 'POST',
                    data    : Qs.stringify({
                        nonce       : this.settings.nonce,
                        action      : 'ajaxSaveSettings',
                        options     : this.models
                    })
                }).then(response => {
                    this.loading = false

                    this.alerts.success.push({
                        key     : this.alerts.success.length + 1,
                        text    : 'Settings saved.'
                    })
                }).catch(error => {
                    console.log(error)
                })
            }
        },
        mounted() {
            this.getSettings()
        }
    }
</script>