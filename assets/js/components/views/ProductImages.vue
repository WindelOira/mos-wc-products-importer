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
            type="success">
                {{ success.text }}
                <v-btn 
                    v-if="success.id && success.restore" 
                    @click="restoreFile(success.id)" 
                    text small 
                    class="float-right">Undo</v-btn>
        </v-alert>

        <v-list 
            v-if="models.files.length" 
            class="mos-uploaded-files--container mt-3">
            <v-list-item
                v-for="file in models.files"
                :key="file.name" 
                dense>
                <v-list-item-avatar>
                    <v-icon
                        v-if="file.type == 'application/x-zip-compressed'" 
                        class="grey lighten-1 white--text">mdi-folder-zip</v-icon>
                    <v-icon
                        v-else 
                        class="grey lighten-1 white--text">mdi-image</v-icon>
                </v-list-item-avatar>

                <v-list-item-content>
                    <v-list-item-title v-text="file.name"></v-list-item-title>
                    <v-list-item-subtitle>{{ file.size | prettyBytes }}</v-list-item-subtitle>
                </v-list-item-content>

                <v-list-item-action>
                    <v-btn 
                        v-if="file.success" 
                        icon>
                        <v-icon color="green">mdi-check-circle-outline</v-icon>
                    </v-btn>
                    <v-btn
                        v-else-if="file.error" 
                        icon>
                        <v-icon color="red">mdi-close-circle-outline</v-icon>
                    </v-btn>
                    <v-btn
                        v-else 
                        icon>
                        <v-progress-circular 
                            :width="2" 
                            :size="20" 
                            indeterminate 
                            color="green"></v-progress-circular>
                    </v-btn>
                    </v-btn>
                </v-list-item-action>
            </v-list-item>
        </v-list>

        <v-card class="mt-4">
            <v-container>
                <v-toolbar
                    :extended="false"
                    :prominent="false"
                    :dense="false"
                    :collapse="false"
                    :flat="false"
                    :extension-height="40" 
                    class="mb-5">
                    <v-btn 
                        v-if="!models.uploaded.loading && models.uploaded.active.folder != ''" 
                        @click="getUploadedFiles('', models.uploaded.active.status)" 
                        icon>
                        <v-icon>mdi-arrow-left</v-icon>
                    </v-btn>

                    <v-toolbar-title>
                        Uploaded Images 
                        <span 
                            v-if="models.uploaded.active.folder != ''">
                            <v-icon>mdi-chevron-right</v-icon> {{ models.uploaded.active.folder }}
                        </span>
                    </v-toolbar-title>

                    <v-spacer></v-spacer>
                    <v-spacer></v-spacer>

                    <v-btn
                        @click="getUploadedFiles('', 'inherit')" 
                        :text="models.uploaded.active.status == 'inherit' ? false : true" 
                        color="success" 
                        small 
                        class="mr-1">Published</v-btn>
                    <v-btn 
                        @click="getUploadedFiles('', 'trash')" 
                        :text="models.uploaded.active.status == 'trash' ? false : true" 
                        color="error" 
                        small>Trashed</v-btn>
                    <v-text-field
                        v-model="models.search"
                        append-icon="mdi-magnify"
                        label="Search"
                        single-line
                        hide-details 
                        class="pt-0 mt-0 ml-3"></v-text-field>

                    <v-spacer></v-spacer>
                    <v-spacer></v-spacer>

                    <v-btn 
                        v-if="models.uploaded.active.status == 'inherit'"
                        @click="models.showUploader = !models.showUploader" 
                        color="primary" 
                        text small 
                        class="mr-1">
                        <v-icon 
                            small 
                            class="mr-1">mdi-upload</v-icon> Upload Files
                    </v-btn>
                    <v-btn 
                        @click="deleteFile(models.uploaded.selected, models.uploaded.active.status == 'inherit' ? false : true)" 
                        :disabled="!models.uploaded.selected.length"
                        color="error" 
                        text small>
                        <v-icon
                            small 
                            class="mr-1">mdi-delete-outline</v-icon> Bulk Delete
                    </v-btn>
                    <v-btn 
                        v-if="models.uploaded.active.status == 'trash'" 
                        @click="restoreFile(models.uploaded.selected)" 
                        :disabled="!models.uploaded.selected.length" 
                        color="green" 
                        text small>
                        <v-icon 
                            small 
                            class="mr-1">mdi-restore</v-icon> Bulk Restore
                    </v-btn>
                </v-toolbar>

                <file-upload 
                    v-model="models.files" 
                    v-if="models.uploaded.active.status != 'trash' && models.showUploader"
                    :data="{nonce: settings.nonce, action: 'ajaxUpload', updateProducts: models.updateProducts}" 
                    :post-action="settings.actions.post" 
                    :extensions="['jpg', 'gif', 'png', 'tif', 'zip']" 
                    :drop="true"
                    :multiple="true" 
                    :chunk-enabled="true" 
                    :chunk="{minSize: 5242880}"
                    @input="uploadFiles" 
                    @input-file="filesUploaded" 
                    ref="upload" 
                    class="mos-upload-files--drop mt-2 mb-4 d-block">Drop files here to upload</file-upload>

                <v-switch 
                    v-model="models.updateProducts" 
                    v-if="models.uploaded.active.status != 'trash' && models.showUploader"
                    label="Update products images ?"></v-switch>
    
                <v-progress-linear
                    v-if="models.uploaded.loading" 
                    indeterminate
                    color="green" 
                    class="mb-4"></v-progress-linear>

                <v-row
                    v-if="!models.uploaded.loading" 
                    class="mos-uploaded-files">
                    <v-col 
                        v-for="(folder, key) in models.uploaded.folders" 
                        :key="'folder-'+ key" 
                        cols="12" 
                        sm="6" 
                        md="4" 
                        lg="3" 
                        class="pt-0">
                        <v-card 
                            @click="0 <= models.uploaded.selected.indexOf(folder.id) ? models.uploaded.selected.splice(models.uploaded.selected.indexOf(folder.id), 1) : models.uploaded.selected.push(folder.id)" 
                            :color="0 <= models.uploaded.selected.indexOf(folder.id) ? 'grey lighten-3' : ''">
                            <v-card-text>
                                <v-row 
                                    justify="space-between">
                                    <v-col 
                                        cols="auto" 
                                        class="pt-2 pb-1">
                                        <v-icon 
                                            v-if="0 <= models.uploaded.selected.indexOf(folder.id)" 
                                            color="green"
                                            small 
                                            class="mr-1 ml-n2">mdi-check-circle</v-icon>
                                        <v-icon class="mr-3">mdi-folder</v-icon> 
                                        <span class="file-name">{{ folder.folder }}</span>
                                    </v-col>

                                    <v-col
                                        cols="auto"
                                        class="text-center pl-0 pt-1 pb-0">
                                        <v-btn 
                                            v-if="0 <= models.uploaded.selected.indexOf(folder.id)" 
                                            @click.stop="deleteFile(folder.id, models.uploaded.active.status == 'trash' ? true : false)" 
                                            color="red" 
                                            fab x-small dark>
                                            <v-icon v-if="models.uploaded.active.status == 'inherit'">mdi-delete-outline</v-icon>
                                            <v-icon v-else>mdi-delete-forever-outline</v-icon>
                                        </v-btn>
                                        <v-btn 
                                            v-if="models.uploaded.active.status == 'trash'" 
                                            @click.stop="restoreFile(folder.id)" 
                                            color="green" 
                                            fab x-small dark 
                                            class="ml-1">
                                            <v-icon>mdi-restore</v-icon>
                                        </v-btn>
                                        <v-btn 
                                            v-if="models.uploaded.active.status == 'inherit'" 
                                            @click.stop="getUploadedFiles(folder.folder)" 
                                            icon>
                                            <v-icon>mdi-chevron-right</v-icon>
                                        </v-btn>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col 
                        v-for="(item, key) in models.uploaded.items" 
                        :key="'file-'+ item.id" 
                        cols="12" 
                        sm="6" 
                        md="4" 
                        lg="3" 
                        class="pt-0">
                        <v-card 
                            @click="0 <= models.uploaded.selected.indexOf(item.id) ? models.uploaded.selected.splice(models.uploaded.selected.indexOf(item.id), 1) : models.uploaded.selected.push(item.id)" 
                            :color="0 <= models.uploaded.selected.indexOf(item.id) ? 'grey lighten-3' : ''">
                            <v-card-text>
                                <v-row 
                                    justify="space-between">
                                    <v-col 
                                        cols="auto" 
                                        class="py-0">
                                        <v-icon 
                                            v-if="0 <= models.uploaded.selected.indexOf(item.id)" 
                                            color="green"
                                            small 
                                            class="mr-1 ml-n2">mdi-check-circle</v-icon>
                                        <v-avatar
                                        class="mr-2"
                                        size="40"
                                        tile>
                                            <v-img :src="item.file.url"></v-img>
                                        </v-avatar>
                                        <span class="file-name">{{ item.file.title }}</span>
                                    </v-col>
                                    <v-col
                                        cols="auto"
                                        class="text-center pl-0 pt-1 pb-0">
                                        <v-btn 
                                            v-if="0 <= models.uploaded.selected.indexOf(item.id)" 
                                            @click.stop="deleteFile(item.id, models.uploaded.active.status == 'trash' ? true : false)" 
                                            color="red" 
                                            fab x-small dark>
                                            <v-icon v-if="models.uploaded.active.status == 'inherit'">mdi-delete-outline</v-icon>
                                            <v-icon v-else>mdi-delete-forever-outline</v-icon>
                                        </v-btn>
                                        <v-btn 
                                            v-if="models.uploaded.active.status == 'trash'" 
                                            @click.stop="restoreFile(item.id)" 
                                            color="green" 
                                            fab x-small dark 
                                            class="ml-1">
                                            <v-icon>mdi-restore</v-icon>
                                        </v-btn>
                                    </v-col>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <v-row 
                    v-if="(0 == models.uploaded.folders.length && 0 == models.uploaded.items.length) && !models.uploaded.loading">
                    <v-col 
                        cols="12" 
                        class="text-center">No files found.</v-col>
                </v-row>
            </v-container>
        </v-card>
    </div>
</template>

<script>
    Vue.component('file-upload', VueUploadComponent)
    
    module.exports = {
        components  : {
            'file-upload'   : VueUploadComponent
        },
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
                models      : {
                    showUploader    : false,
                    updateProducts  : true,
                    search          : '',
                    files           : [],
                    uploaded        : {
                        loading         : true,
                        selected        : [],
                        active          : {
                            folder          : '',
                            status          : 'inherit'
                        },
                        folders         : [],
                        items           : []
                    }
                }
            }
        },
        methods     : {
            getUploadedFiles(folder = '', status = 'inherit') {
                this.models.uploaded.loading = true
                this.models.uploaded.selected = []

                axios({
                    url     : this.settings.actions.post,
                    method  : 'POST',
                    data    : Qs.stringify({
                        nonce       : this.settings.nonce,
                        action      : 'ajaxGetUploads',
                        folder      : folder,
                        status      : status
                    })
                }).then(response => {
                    if( response.data.result ) {
                        this.models.uploaded.folders = response.data.files.filter(file => {
                            return file.folder != ''
                        })
                        this.models.uploaded.items = response.data.files.filter(file => {
                            return file.folder == ''
                        })

                        this.models.uploaded.loading = false
                    } else {
                        this.models.uploaded.folders = []
                        this.models.uploaded.items = []
                    }

                    this.models.uploaded.active.folder = folder
                    this.models.uploaded.active.status = status
                    this.models.uploaded.loading = false
                }).catch(error => {
                    console.log(error)
                })
            },
            uploadFiles(value) {
                value.forEach(v => {
                    if( v.file.type == 'application/x-zip-compressed' ) {
                        this.$refs.upload.remove(v)
                        
                        JSZip.loadAsync(v.file).then(zip => {
                            // var zipName = v.file.name.replace('.'+ v.file.name.split('.').pop(), ''),
                            var re = /(.jpg|.png|.gif|.tif|.ps|.jpeg)$/,
                                promises = Object.keys(zip.files).filter(fileName => {
                                    // don't consider non image files
                                    return re.test(fileName.toLowerCase())
                                }).map(fileName => {
                                    var file = zip.files[fileName]

                                    return file.async('blob').then(blob => {
                                        var fileType = fileName.split('.').pop()

                                        return {
                                            blob    : blob,
                                            name    : fileName,
                                            type    : 'image/'+ fileType
                                        }
                                    })
                                })
                            // `promises` is an array of promises, `Promise.all` transforms it
                            // into a promise of arrays
                            return Promise.all(promises)
                        }).then(result => {
                            if( 0 < result.length ) {
                                result.forEach(r => {
                                    var file = new window.File([r.blob], r.name, {
                                        type    : r.type
                                    })

                                    this.$refs.upload.add(file)
                                    this.$refs.upload.active = true
                                })
                            }
                        }).catch(error => {
                            console.error(error)
                        })

                        this.$refs.upload.active = true
                    } else {
                        this.$refs.upload.active = true
                    }
                })
                
            },
            filesUploaded(newFile, oldFile) {
                // Add file
                if( newFile && !oldFile ) {
                    
                }

                // Update file
                if( newFile && oldFile ) {
                    // Start upload
                    if( newFile.active !== oldFile.active ) {
                        // console.log('Start upload', newFile.active, newFile)

                        // min size
                        // if( newFile.size >= 0 && newFile.size < 100 * 1024 ) {
                        //     newFile = this.$refs.upload.update(newFile, {error: 'size'})
                        // }
                    }

                    // Upload progress
                    if( newFile.progress !== oldFile.progress ) {
                        // console.log('progress', newFile.progress, newFile)
                    }

                    // Upload error
                    if( newFile.error !== oldFile.error ) {
                        // console.log('error', newFile.error, newFile)
                    }

                    // Uploaded successfully
                    if( newFile.success !== oldFile.success ) {
                        if( newFile.response.file.files.length ) {
                            if( newFile.response.file.folder == '' ) {
                                this.models.uploaded.items = this.models.uploaded.items.concat(newFile.response.file.files)
                            } else {
                                this.models.uploaded.folders = this.models.uploaded.folders.concat(newFile.response.file)
                            }
                        }
                    }
                }

                // Remove file
                if( !newFile && oldFile ) {
                    // Automatically delete files on the server
                    if (oldFile.success && oldFile.response.id) {
                        
                    }
                }

                // Automatic upload
                if( Boolean(newFile) !== Boolean(oldFile) || oldFile.error !== newFile.error ) {
                    if( !this.$refs.upload.active ) {
                        this.$refs.upload.active = true
                    }
                }
            },
            deleteFile(id, permanent = false) {
                this.models.uploaded.loading = true

                axios({
                    url     : this.settings.actions.post,
                    method  : 'POST',
                    data    : Qs.stringify({
                        nonce       : this.settings.nonce,
                        action      : 'ajaxDeleteUpload',
                        data        : {
                            id          : id,
                            permanent   : permanent
                        }
                    })
                }).then(response => {
                    if( response.data.result ) {
                        if( 0 < response.data.files.length ) {
                            response.data.files.forEach(file => {
                                this.alerts.success.push({
                                    id          : !permanent ? file.id : false,
                                    text        : !permanent ? file.file.title +' has been moved to trash.' : file.file.title +' has been deleted permanently.',
                                    restore     : true
                                })
                                
                                this.models.uploaded.folders = this.models.uploaded.folders.filter(folder => {
                                    return folder.id == file.id ? false : true
                                })
                                this.models.uploaded.items = this.models.uploaded.items.filter(item => {
                                    return item.id == file.id ? false : true
                                })
                            })

                            this.models.uploaded.selected = []
                            this.models.uploaded.loading = false
                        }
                    }
                    
                }).catch(error => {
                    console.log(error)
                })
            },
            restoreFile(id) {
                this.models.uploaded.loading = true

                axios({
                    url     : this.settings.actions.post,
                    method  : 'POST',
                    data    : Qs.stringify({
                        nonce       : this.settings.nonce,
                        action      : 'ajaxRestoreUpload',
                        data        : {
                            id          : id
                        }
                    })
                }).then(response => {
                    if( response.data.result ) {
                        if( 0 < response.data.files.length ) {
                            response.data.files.forEach(file => {
                                this.alerts.success.push({
                                    id          : file.id,
                                    text        : file.file.title +' has been restored.'
                                })

                                if( this.models.uploaded.active.status == 'inherit' ) {
                                    this.models.uploaded.items.push(file)
                                } else {
                                    this.models.uploaded.folders = this.models.uploaded.folders.filter(folder => {
                                        return folder.id == file.id
                                    })
                                    this.models.uploaded.items = this.models.uploaded.items.filter(item => {
                                        return item.id == file.id ? false : true
                                    })
                                }

                                this.alerts.success = this.alerts.success.filter(success => {
                                    return success.id == file.id && success.restore ? false : true
                                })
                            })

                            this.models.uploaded.selected = []
                            this.models.uploaded.loading = false
                        }
                    }
                }).catch(error => {
                    console.log(error)
                })
            }
        },
        created() {
            this.getUploadedFiles()
        }
    }
</script>