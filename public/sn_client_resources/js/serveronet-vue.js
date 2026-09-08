const { createApp, ref } = Vue
app = null

function tfyn($b) {
    $b ? $s = 'Yes' : $s = 'No';
    return $s;
}

function getCookieValueByName(name) {
  var match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
  return (match ? decodeURIComponent(match[3]) : null);
}

function copyToClipboard(text) {
  if (!navigator.clipboard) {
    alert('Cannot copy to clipboard. Please copy manually.')
    toastr.error('Cannot copy to clipboard. Please copy manually.')
  } else {
    navigator.clipboard.writeText(text)
    toastr.success('Copied.')
  }
}

function handleErrorResponse(error) {
  response = error.response
  console.log(response.status)

  if (error.response) {
    if (response.status == 419) {
      if (confirm('This page has expired. Reloading.') == true) {
        window.location.reload()
      }
    } else if (response.status == 401) {
      if (confirm('This page has expired.\nPlease re-login.') == true) {
        window.location.href = './admin_logout'
      }
    } else if (response.status == 429) {
      alert('Too Many Attempts. Try again later.')
    } else {
      alert('Error ' + response.data)
    }
  } else if (error.request) {
    alert('Error ' + error.request)
    console.log(error.request);
  } else {
    console.log('Error', error.message);
    alert('Error ' + error.message)
  }
}

function confirmDelete(id, scope) {
  if (confirm("Confirm Delete!") == true) {
    axios.post("/admin/destroy_by_id",
      {
        "ids": [id],
        "scope": scope,
      }
    ).then(function successCallback(response) {
      if (response.data.success === true) {
        Livewire.dispatch('pg:eventRefresh-' + scope)
        toastr.success('Deleted')
      } else {
        alert('Error: ' + response.data.message)
      }
    }, function errorCallback(response) {
      alert('Error: ' + response.data.message)
    });
  }
}

/* RecentIdentity */
createApp({
  data() {
    return {
      visitors: [{ "visitor_id": "", "alias": "", "color": "#e5e5e5", "short": "Loading recent identities..." }],
    }
  },
  mounted() {

    window.addEventListener('message', this.handleMessage, false);

  },
  methods: {
    handleMessage(event) {
      if (event.data === 'loaded') {
        this.postRequestForIdentities()
      } else {
        if ('recent_identities' in event.data) {
          this.visitors = event.data.recent_identities
          console.log('recent_identities Message received  Origin/Data: ', event.origin + ' ' + JSON.stringify(event.data));
          console.log('Message received from iframe Source:', event.source);
        }
      }
    },
    postRequestForIdentities() {
      myIframe = document.getElementById('recent_identities_iframe').contentWindow
      myIframe.postMessage('requesting_recent_identities', '*')
    },
    recentIdentitySelected(visitor_id) {
      document.getElementById('visitor_id').value = visitor_id;
      document.getElementById('visitor_id').style.border = '2px solid darkgrey'
    }
  }
}).mount('#appRecentIdentity')
// -------------------------

/* Recent Identity Control Panel */
createApp({
  data() {
    return {
      visitors: [],
    }
  },
  mounted() {
    parent.postMessage('loaded', '*')
    window.addEventListener('message', this.handleMessage, false);
  },
  methods: {
    handleMessage(event) {
      if (event.data === 'requesting_recent_identities') {
        console.log('requesting_recent_identities Message received  Origin/Data: ', event.origin + ' ' + event.data);
        console.log('Message received from iframe Source:', event.source);
        console.log('Message received from iframe Data:', uiDomains);
        const url = new URL(event.origin);

        event_origin_hostname = url.hostname

        const allowed = Object.values(uiDomains).some(domain =>
          event_origin_hostname.endsWith(domain)
        );

        if (allowed) {
          console.log("Allowed origin:", event.origin);
          recentIdentitiesMessage = { 'recent_identities': existingRecentVisitorsFromCookie }
          event.source.postMessage(recentIdentitiesMessage, '*')
        } else {
          console.log("Blocked origin:", event.origin);
        }
      }
    },

  }
}).mount('#appRecentIdentityControlPanel')
// -------------------------

/* ExternalURL */
createApp({
  data() {
    return {
      isPublicSelfAddressOverridden: false
    }
  }
}).mount('#appExternalURL')
// -------------------------

/* Settings */
createApp({
  data() {
    return {
      searchTerm: '',
      settingsList: ref([]),
      showAdvanced: false,
      operationInProgress: true
    }
  },
  mounted() {
    this.reload();
  },
  methods: {
    filteredList() {
      if (this.searchTerm == '')
        return this.settingsList

      return this.settingsList.filter((item) => {
        return (item.setting_id.includes(this.searchTerm) || item.desc.includes(this.searchTerm))
      })
    },
    async reload() {
      this.operationInProgress = true
      response = await axios.get(clientRoot + "admin/get_settings",)
        .catch((error) => {
          handleErrorResponse(error);
        })
        .finally(function () {
          this.operationInProgress = false
        });
      console.log(response)
      this.settingsList = response.data
      this.operationInProgress = false
    },
    async save(setting_id) {
      this.operationInProgress = true
      setting = this.settingsList.filter(setting => setting.setting_id == setting_id)[0]
      setting_value = setting.value
      response = await axios.post(clientRoot + "admin/update_setting", {
        "setting_id": setting_id, "setting_value": setting_value
      })
        .catch((error) => {
          handleErrorResponse(error);
        })
        .finally(function () {
          this.operationInProgress = false
        });
      console.log(response)
      if (response.data.data == "true") {
        setting.result = '✅'
        setTimeout(() => { setting.result = '' }, 2000);
        toastr.success('Saved')
      }
      this.operationInProgress = false
    }
  }
}).mount('#appSettings')
// -------------------------

/* CheckMysqlConnection */
createApp({
  data() {
    return {
      password: '',
      password_confirmation: '',
      showDevNodeConfig: false,
      db_checking_state: 'Ready to check DB conectivity',
      successLabel: 'Success',
      DB_HOST: '127.0.0.1',
      DB_PORT: '3306',
      DB_DATABASE: 'database_name',
      DB_USERNAME: 'root',
      DB_PASSWORD: '',
      DB_TABLE_PREFIX: 'sn_',

      siteDatabasesInMySql: false,
      db_type: {
        name: 'sqlite'
      }
    }
  },
  methods: {
    async checkMysqlConnection() {
      this.db_checking_state = 'Pending'

      response = await axios.post(clientRoot + "firstrun/check_mysql_connectivity", {
        "DB_HOST": this.DB_HOST,
        "DB_PORT": this.DB_PORT,
        "DB_DATABASE": this.DB_DATABASE,
        "DB_USERNAME": this.DB_USERNAME,
        "DB_PASSWORD": this.DB_PASSWORD,
        "DB_TABLE_PREFIX": this.DB_TABLE_PREFIX,
        "siteDatabasesInMySql": this.siteDatabasesInMySql,
      })
        .catch((error) => {
          handleErrorResponse(error);
        });
      console.log(response)
      if (response.data.success == true) {
        this.db_checking_state = this.successLabel
        toastr.success('Successfully connected to MySql. Site Databases In MySql: '+tfyn(this.siteDatabasesInMySql))
      } else {
        this.db_checking_state = 'Error: ' + response.data.message
      }
    },

    isDbConnectionCorrect() {
      return this.db_type.name == 'mysql' && this.db_checking_state != this.successLabel
    },
    setPasswordForTest() {
      passwordForTests = 'Password4Tests123!'
      this.password = passwordForTests
      this.password_confirmation = passwordForTests
    }
  }
}).mount('#appCheckMysqlConnection')
// -------------------------

/* Retrieval - appRetrieval */
createApp({
  data() {
    return {
      maxRetries: 5,
      retriesCount: 0,
      isSpinning: true,
      state: 'Checking...',
      state_debug: 'State debug',
      error_state: 'Failed to Retrieve - Giving up',
      markerStates: ['.', '..', '...', '....', '.....', '......', '.......', '.......!'],
      currentMarkerState: 0,
      marker: '',
    }
  },
  mounted() {
    app = this
  },
  methods: {
    async checkAvailabilityState() {
      this.retriesCount++

      response = await axios.get(target + '?states_only=1', { "states_only": 1 })
        .catch((error) => {
          handleErrorResponse(error);
        });

       if (response.data.success == true) {
         this.state = response.data.data.stateForVisitor + ' Retry: ' + this.retriesCount + '/' + this.maxRetries
         this.state_debug = response.data.debug_data + ' | Retries: ' + this.retriesCount + ' of ' + this.maxRetries
 
         if (response.data.data.continue == true) {
          this.isSpinning = false;           
          window.location.href = target

         } else {
          if (this.retriesCount == this.maxRetries) this.isSpinning = false;
           console.log('scheduling retry')
           setTimeout(() => {
             if (this.retriesCount < this.maxRetries) {
               this.checkAvailabilityState()
             }
           }, 2000);
         }
       } else {
         console.log('scheduling retry')
         setTimeout(() => {
           if (this.retriesCount < this.maxRetries) {
             this.checkAvailabilityState()
           } else {
             this.isSpinning = false;
           }
         }, 2000);
       }

      response = await axios.get(target + '?showRetrievalPageForIndex=0', { "showRetrievalPageForIndex": 0 })
    },
    getUpdatedMarker() {
      if (this.currentMarkerState == this.markerStates.length - 1) {
        this.currentMarkerState = 0
      } else {
        this.currentMarkerState++
      }
      return this.markerStates[this.currentMarkerState]
    },
    refreshMarker() {
      console.log('refreshMarker ' + this.retriesCount + ' ' + this.maxRetries)
      setTimeout(
        () => {
          if (this.retriesCount < this.maxRetries) {
            this.marker = this.getUpdatedMarker()
            this.refreshMarker()
          }
        }, 1000);
    }
  },
  mounted() {
    this.refreshMarker()
    setTimeout(() => {
      this.checkAvailabilityState()
    }, 500);
  }
}).mount('#appRetrieval')
// -------------------------

/* IdentityUpload */
createApp({
  data() {
    return {
      isPublicSelfAddressOverridden: false,
      identity: 'default_identity',
      visitor_id: new_visitor_id,
      base64_seed: new_base64_seed,
    }
  },
  mounted() {
    setTimeout(() => {
      console.log('mounted' + this.visitor_id + ' ' + this.base64_seed)
    }, 2000);
  },
  methods: {
    onBase64SeedChange(event) {
      this.visitor_id = ''
    },
    handleFileUpload(event) {
      const file = event.target.files[0]; if (file) {
        this.upload()
      }
    },
    upload() {
      file = document.getElementById('file_picker').files[0],
        fileReader = new FileReader();
      fileReader.onload = (event) => {
        this.fileContents = event.target.result;
        try {
          JSON.parse(this.fileContents)
        } catch (error) {
          alert('Identity file corrupted!')
          return
        }
        this.identity = JSON.parse(this.fileContents)
        this.visitor_id = this.identity.visitor_id
        this.alias = this.identity.alias
        this.base64_seed = this.identity.base64_seed

        popup = {
          title: 'Identity Imported',
          html_content: 'Identity Imported.'
            + '<br>Ready to register.'
        }

        toastr.success('Imported. Ready to register.', {timeOut: 10000})
      };
      fileReader.readAsText(file);
    }
  }
}).mount('#appIdentityUpload')
// -------------------------