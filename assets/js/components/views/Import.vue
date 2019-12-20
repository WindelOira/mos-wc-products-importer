<template>
    <div>
        <v-alert 
            v-for="error in alerts.errors" 
            :key="error.text"  
            type="error">{{ error.text }}</v-alert>

        <v-stepper v-model="wizard.step">
            <v-stepper-header>
                <v-stepper-step 
                    v-for="step in wizard.steps" 
                    :key="step.position"
                    :complete="wizard.step > step.position" 
                    :step="step.position">{{ step.text }}</v-stepper-step>
            </v-stepper-header>

            <v-progress-linear
                :active="data.loading" 
                indeterminate 
                color="green"></v-progress-linear>

            <v-stepper-items>
                <v-stepper-content step="1">
                    <v-form @submit.prevent="generatePreview">
                        <v-file-input 
                            v-model="models.file" 
                            label="Products File"></v-file-input>

                        <v-checkbox
                            v-model="models.skipMapping"
                            :label="'Skip data mapping'" 
                            hide-details></v-checkbox>

                        <v-checkbox 
                            v-model="models.skipImageSource" 
                            :label="'Skip image source'" 
                            hide-details></v-checkbox>

                        <v-btn 
                            color="primary" 
                            class="mt-7 mb-1" 
                            type="submit">Submit</v-btn>
                    </v-form>
                </v-stepper-content>

                <v-stepper-content step="2">
                    <v-simple-table
                        :dense="false"
                        :fixed-header="false"
                        :height="300">
                        <template v-slot:default>
                            <thead>
                                <tr>
                                    <th 
                                        v-for="(th, thIndex) in data.map.headers" 
                                        :key="`th-${thIndex}`" 
                                        class="text-left">{{ th }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="(tr, trIndex) in data.items.slice(0, 20)" 
                                    :key="`tr-${trIndex}`">
                                    <td
                                        v-for="(td, tdIndex) in data.map.headers" 
                                        :key="`td-${tdIndex}-tr-${trIndex}`">{{ tr[td] }}</td>
                                </tr>
                            </tbody>
                        </template>
                    </v-simple-table>

                    <v-btn 
                        @click="stepTo(1)"
                        class="mt-3 mr-2 mb-1">Return</v-btn>

                    <v-btn
                        @click="stepTo(3)" 
                        color="primary" 
                        class="mt-3 mb-1">Continue</v-btn>
                </v-stepper-content>

                <v-stepper-content step="3">
                    <v-card dark>
                        <v-container class="py-0 mb-5">
                            <v-row>
                                <v-col>Map To</v-col>
                                <v-col>Column Header</v-col>
                                <v-col>Example Column Value</v-col>
                            </v-row>
                        </v-container>
                    </v-card>

                    <v-row 
                        v-for="(legend, legendIndex) in data.map.legend" 
                        :key="`${legendIndex}-${legend.key}`">
                        <v-col>
                            <v-select 
                                v-model="legend.selected"
                                :items="data.map.headers" 
                                :hide-details="true"></v-select>
                        </v-col>
                        <v-col>
                            <h4 class="my-4">{{ legend.key }}</h4>
                        </v-col>
                        <v-col>
                            <h4 class="my-4">{{ 0 < data.items.length ? data.items[0][legend.selected] : '' }}</h4>
                        </v-col>
                    </v-row>

                    <v-btn 
                        @click="stepTo(2)"
                        class="mt-3 mr-2 mb-1">Return</v-btn>

                    <v-btn
                        @click="stepTo(4); getImageFolders()" 
                        color="primary" 
                        class="mt-3 mb-1">Next</v-btn>
                </v-stepper-content>
            </v-stepper-items>

            <v-stepper-content step="4">    
                <v-row>
                    <v-col 
                        v-for="(folder, key) in data.imagesSource" 
                        :key="key" 
                        cols="12" 
                        sm="6" 
                        md="4" 
                        lg="3" 
                        class="pt-0">
                        <v-card 
                            @click="(-1 < data.sources.indexOf(folder)) ? data.sources.splice(data.sources.indexOf(folder), 1) : data.sources.push(folder)" 
                            class="d-flex align-center">
                            <v-btn 
                                :color="(-1 < data.sources.indexOf(folder)) ? 'green' : 'grey lighten-1'" 
                                icon 
                                dark 
                                absolute 
                                top 
                                right>
                                <v-icon>{{ (-1 < data.sources.indexOf(folder)) ? 'mdi-check-circle' : 'mdi-check-circle-outline' }}</v-icon>
                            </v-btn>
                            <v-card-text 
                                class="py-6">
                                <v-icon class="mr-3">mdi-folder</v-icon> {{ folder }}
                            </v-card-text>    
                        </v-card>
                    </v-col>
                </v-row>

                <v-btn 
                    @click="stepTo(3)"
                    class="mt-3 mr-2 mb-1">Return</v-btn>

                <v-btn
                    @click="doImport(5)" 
                    color="primary" 
                    class="mt-3 mb-1">Import</v-btn>
            </v-stepper-content>

            <v-stepper-content step="5" class="text-center">
                <div v-if="data.cleaningUp">
                    <v-progress-circular
                        :value="((data.unimported.deleted / data.unimported.items.length) * 100)" 
                        :size="150"
                        :width="20"
                        color="orange"></v-progress-circular>
                    <h3 class="mb-0" v-if="data.cleaningUp">Cleaning up...</h3>
                </div>

                <div v-else>
                    <v-progress-circular
                        :value="((data.imported.items.length / data.items.length) * 100)" 
                        :size="150"
                        :width="20"
                        color="green"></v-progress-circular>
                    <h3 class="mb-0">{{ data.imported.items.length }} of {{ data.items.length }}</h3>
                </div>
            </v-stepper-content>

            <v-stepper-content step="6">
                <v-list-item 
                    v-for="(item, itemIndex) in data.imported.items.slice(((data.imported.page * 5) - 5), data.imported.page == 1 ? (data.imported.page * 5) : ((data.imported.page + 1) * 5))"  
                    :key="itemIndex">
                    <v-list-item-avatar>
                        <v-img :src="item.image"></v-img>
                    </v-list-item-avatar>
                    <v-list-item-content>
                        <v-list-item-title>{{ item.title }}</v-list-item-title>
                        <v-list-item-subtitle 
                            v-if="!item.is_image">Product ID: #{{ item.id }} | SKU: {{ item.sku }}</v-list-item-subtitle>
                    </v-list-item-content>
                    <v-list-item-action 
                        v-if="!item.is_image" 
                        class="mx-0 d-block">
                        <v-btn 
                            v-if="item.url" 
                            :href="item.url" 
                            target="_blank"
                            color="green" 
                            text link small 
                            class="px-0">View</v-btn>
                        <v-btn 
                            v-if="item.edit_url" 
                            :href="item.edit_url" 
                            target="_blank" 
                            color="primary" 
                            text link small 
                            class="px-0 float-left">Edit</v-btn>
                    </v-list-item-action>
                </v-list-item>

                <v-pagination 
                    v-model="data.imported.page" 
                    :length="Math.ceil(parseInt(data.imported.items.length) / 5)"></v-pagination>

                <v-btn 
                    @click="initialize()" 
                    color="primary" 
                    class="mt-3 mb-1 ml-3">Finish</v-btn>
            </v-stepper-content>
        </v-stepper>
    </div>
</template>

<script>
    module.exports = {
        data() {
            return {
                alerts  : {
                    success     : [],
                    errors      : []
                },
                wizard  : {
                    step    : 1,
                    steps   : [
                        {position: 1, text: 'Import File'},
                        {position: 2, text: 'Preview'},
                        {position: 3, text: 'Map Fields'},
                        {position: 4, text: 'Images Source'},
                        {position: 5, text: 'Importing'},
                        {position: 6, text: 'Finished'}
                    ]
                },
                models  : {
                    file                : null,
                    skipMapping         : true,
                    skipImageSource     : true,
                    select              : null
                },
                data    : {
                    time            : new Date().getTime(),
                    loading         : false,
                    importing       : false,
                    cleaningUp      : false,
                    hasImageLinks   : false,
                    importProducts  : true,
                    imagesSource    : [],
                    items           : [],
                    sources         : [],
                    map             : {
                        headers         : [],
                        legend          : [
                            {key:'category', selected:'__EMPTY'},
                            {key:'category_child', selected:'__EMPTY_1'},
                            {key:'category_grandchild', selected:'__EMPTY_2'},
                            {key:'title', selected:'Description'},
                            {key:'slug', selected:'Description'},
                            {key:'description', selected:'Description'},
                            {key:'status', selected:1},
                            {key:'sku', selected:'Code'},
                            {key:'stock', selected:null},
                            {key:'regular_price', selected:'Del Price'},
                            {key:'sale_price', selected:null},
                            {key:'tax_class', selected:'GST'},
                            {key:'supplier_part_number', selected:'Supplier Part No.'},
                            {key:'image_link', selected:'Image Link'},
                            {key:'barcode', selected:'Barcode'},
                            {key:'inner_barcode', selected:'Inner Barcode'},
                            {key:'uom', selected:'Units'},
                            {key:'ppu', selected:'PPU'},
                        ]
                    },
                    unimported      : {
                        items           : [],
                        batch           : 1,
                        deleted         : 0
                    },
                    imported        : {
                        page            : 1,
                        batch           : 1,
                        items           : []
                    }
                }
            }
        },
        methods     : {
            stepTo(s) {
                this.wizard.step = s
            },
            initialize() {
                location.reload(true)
            },
            getProductTaxonomies(builtin = false) {
                axios({
                    url     : mosWC.ajax.url,
                    method  : 'POST',
                    data    : Qs.stringify({
                        action      : 'getProductTaxonomies',
                        data        : {
                            nonce       : mosWC.ajax.nonce,
                            builtin     : builtin
                        }
                    })
                }).then(response => {
                    if( 0 < response.data.taxonomies.length ) {
                        var pos = 3
                        response.data.taxonomies.forEach(taxonomy => {
                            this.data.map.legend.splice(pos, 0, {
                                key         : taxonomy,
                                selected    : taxonomy.indexOf('brand') ? 'brand' : taxonomy
                            })
                            pos++
                        })
                    }
                }).catch(error => {
                    console.log(error)
                })
            },
            validateFile(file) {
                if( file.type == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) {
                    return 'xlsx'
                } else if( file.type == 'application/vnd.ms-excel' || file.type == 'text/csv' ) {
                    return 'csv'
                } else {
                    return false
                }
            },
            generatePreview(evt) {
                this.alerts.errors = []
                
                if( !this.models.file ) {
                    this.alerts.errors.push({text: 'Please select file.'})
                    return false
                }

                var _validatedFile = this.validateFile(this.models.file)

                if( !_validatedFile ) {
                    this.alerts.errors.push({text: 'Invalid file type.'})
                    return false
                } else {
                    this.data.loading = true
                    this.parseFile(this.models.file, _validatedFile)
                }
            },
            parseFile(file, type) {
                var instance = this,
                    reader = new FileReader()

                if( type == 'xlsx' ) {
                    reader.onload = function(e) {
                        var data = e.target.result,
                            workbook = XLSX.read(data, {
                                type: 'binary'
                            })

                        workbook.SheetNames.forEach(function(sheetName) {
                            var headers = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {
                                    header  : 1
                                })

                            for(var h = 0; h <= headers[0].length; h++) {
                                if( typeof headers[0][h] != 'undefined' ) {
                                    instance.data.map.headers.push(headers[0][h])
                                } else {
                                    instance.data.map.headers.push(h == 0 ? '__EMPTY' : '__EMPTY_'+ h)
                                }
                            }

                            if( 0 <= instance.data.map.headers.indexOf('Image Link') ) {
                                instance.data.hasImageLinks = true
                            }

                            instance.data.items = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName])
                        })
                    }

                    reader.onerror = function(ex) {
                        console.log(ex)
                    }

                    reader.onloadend = function() {
                        instance.data.loading = false

                        if( instance.models.skipMapping && instance.models.skipImageSource ) {
                            if( !instance.data.hasImageLinks ) {
                                instance.data.importProducts = true
                                instance.doImport(5)
                            } else {
                                instance.data.importProducts = false
                                instance.doImport(5)
                            }
                        } else if( instance.models.skipMapping && !instance.models.skipImageSource ) {
                            instance.getImageFolders()
                            instance.stepTo(4)
                        } else {
                            instance.stepTo(2)
                        }
                    }

                    reader.readAsBinaryString(file)
                } else if( type == 'csv' ) {
                    Papa.parse(file, {
                        header          : true,
                        complete        : (results) => {
                            this.data.items = results.data.filter(data => {
                                return 'Supplier Part No.' in data
                            })
                            this.data.map.headers = Object.keys(this.data.items[0])
                            this.data.loading = false

                            if( 0 <= this.data.map.headers.indexOf('Image Link') ) {
                                this.data.hasImageLinks = true
                            }

                            if( this.models.skipMapping && this.models.skipImageSource ) {
                                if( !this.data.hasImageLinks ) {
                                    instance.data.importProducts = true
                                    this.doImport(5)
                                } else {
                                    instance.data.importProducts = false
                                    this.doImport(5)
                                }
                            } else if( this.models.skipMapping && !this.models.skipImageSource ) {
                                this.getImageFolders()
                                this.stepTo(4)
                            } else {
                                this.stepTo(2)
                            }
                        }
                    })
                }
            },
            doImport(step = false) {
                if( step ) {
                    this.stepTo(step)
                }
                
                this.data.loading = true

                axios({
                    url     : mosWC.ajax.url,
                    method  : 'POST',
                    data    : Qs.stringify({
                        action      : 'ajaxImport',
                        data        : {
                            nonce           : mosWC.ajax.nonce,
                            items           : this.data.items.slice((this.data.imported.batch - 1), this.data.imported.batch),
                            map             : this.data.map,
                            importProducts  : this.data.importProducts ? 1 : 0,
                            imageSources    : 0 < this.data.sources.length ? this.data.sources : '',
                            time            : this.data.time
                        }
                    })
                }).then(response => {
                    this.data.imported.items = this.data.imported.items.concat(response.data.items)

                    if( response.data.skipped || 
                        this.data.imported.items.length < this.data.items.length ) {
                        this.data.imported.batch += 1
                        this.doImport(step)
                    } else {
                        if( this.data.importProducts ) {
                            this.getUnimported(true)
                        } else {
                            this.data.loading = false

                            this.stepTo(6)
                        }
                    }
                }).catch(error => {
                    console.log(error)
                })
            },
            getUnimported(doDelete = false) {
                axios({
                    url     : mosWC.ajax.url,
                    method  : 'POST',
                    data    : Qs.stringify({
                        action      : 'ajaxGetUnimported',
                        data        : {
                            nonce           : mosWC.ajax.nonce
                        }
                    })
                }).then(response => {
                    if( 0 < response.data.items.length ) {
                        this.data.unimported.items = this.data.unimported.items.concat(response.data.items)

                        if( doDelete ) {
                            this.deleteUnimported(this.data.unimported.items)
                        }
                    } else {
                        this.data.loading = false
                        this.data.cleaningUp = false

                        this.stepTo(6)
                    }
                }).catch(error => {
                    console.log(error)
                })
            },
            deleteUnimported(items) {
                this.data.cleaningUp = true 

                axios({
                    url     : mosWC.ajax.url,
                    method  : 'POST',
                    data    : Qs.stringify({
                        action      : 'ajaxDeleteUnimported',
                        data        : {
                            nonce           : mosWC.ajax.nonce,
                            items           : items.slice((this.data.unimported.batch - 1), this.data.unimported.batch)
                        }
                    })
                }).then(response => {
                    this.data.unimported.batch += 1
                    this.data.unimported.deleted += 1

                    if( this.data.unimported.deleted < this.data.unimported.items.length ) {
                        this.deleteUnimported(items)
                    } else {
                        this.data.unimported.deleted = 0
                        this.data.unimported.items = []
                        this.data.loading = false
                        this.data.cleaningUp = false

                        this.stepTo(6)
                    }
                }).catch(error => {
                    console.log(error)
                })
            },
            getImageFolders() {
                this.data.loading = true

                axios({
                    url     : mosWC.ajax.url,
                    method  : 'POST',
                    data    : Qs.stringify({
                        action      : 'ajaxGetImageFolders',
                        data        : {
                            nonce       : mosWC.ajax.nonce
                        }
                    })
                }).then(response => {
                    if( response.data.result ) {
                        this.data.imagesSource = response.data.folders
                    } else {
                        this.doImport(5)
                    }

                    this.data.loading = false
                }).catch(error => {
                    console.log(error)
                })
            }
        },
        mounted() {
            this.getProductTaxonomies()
        }
    }
</script>