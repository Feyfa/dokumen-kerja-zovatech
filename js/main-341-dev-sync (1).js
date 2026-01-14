/*!

 =========================================================
 * EXACT MATCH MARKETING TEAM 2021
 =========================================================
 
 */
 import VueTour from './plugins/tour';
import Global from './plugins/global';
import Vue from 'vue';
import VueRouter from 'vue-router';
import RouterPrefetch from 'vue-router-prefetch'
import DashboardPlugin from './plugins/dashboard-plugin';
import App from './App.vue';
import moment from 'moment-timezone'; // Import moment-timezone
import swal from 'sweetalert2';
import '@/assets/css/swal-agreement-feature.css';

// router setup
import router from './routes/router';
//import router from './routes/starterRouter';
import i18n from './i18n';
import './registerServiceWorker'
import { store } from './store/store'
import * as GmapVue from 'gmap-vue'

import axios from 'axios'

// Harus 16 karakter = 128 bit
const ZX1000 = CryptoJS.enc.Utf8.parse(process.env.VUE_APP_ENCRYPTION_KEY || 'aB7fD9kL2mXcQ1eZ');
const H2R = CryptoJS.enc.Utf8.parse(process.env.VUE_APP_ENCRYPTION_IV || 'pT6rY8vB0wNqJ4sM');
// const R1M = ['/mailboxpower/upload-zipcode'];

function encryptPayload(data) {
  const stringified = JSON.stringify(data)

  const PANIGALE = CryptoJS.AES.encrypt(stringified, ZX1000, {
    iv: H2R,
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.Pkcs7
  })

  // ciphertext adalah WordArray, harus di-convert ke Base64
  return CryptoJS.enc.Base64.stringify(PANIGALE.ciphertext)
}

// redirect reusable module function to integration
async function redirectToAddIntegration() {
    const pathname = window.location.pathname
    localStorage.setItem('urlCampaign' , pathname)
    if(store.getters.userData.user_type === 'client') {
       router.push({ name: 'Integration List' })
    } else {
       router.push({ name: 'Client List' })
   }
}

// Intercept semua request POST/PUT/PATCH
axios.interceptors.request.use(config => {
  const method = config.method.toUpperCase()
  // const sekips = R1M.some(url => config.url.includes(url))
  // if (['POST', 'PUT', 'PATCH'].includes(method) && config.data && !sekips) {
  //   const dt = encryptPayload(config.data)
  //   config.data = { dt }
  // }
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) && config.data && !(config.data instanceof FormData)) {
    const dt = encryptPayload(config.data)
    config.data = { dt }
  }
  return config
}, error => {
  return Promise.reject(error)
})

// ✅ Set Authorization header dari token di localStorage
const accessToken = localStorage.getItem("access_token");
if (accessToken) {
  axios.defaults.headers.common["Authorization"] = `Bearer ${accessToken}`;
}

// Interceptor auto-refresh
axios.interceptors.response.use(
  response => response,
  async error => {
    if (error.response && error.response.status === 401) {
      const refreshToken = localStorage.getItem("refresh_token");

      if (!refreshToken) {
        // Cek apakah kita masih dalam proses login?
        const isLoggingIn = window.location.pathname.includes('/login');
        if (!isLoggingIn) {
          localStorage.clear();
          window.location.href = "/login";
        }
        return Promise.reject(error);
        // localStorage.clear();
        // window.location.href = "/login";
        // return Promise.reject(error);
      }

      // 🔐 Cegah refresh token ganda
      if (store.state.isRefreshing) {
        // Tunggu sampe refresh selesai (bisa bikin queue kalau mau lebih kompleks)
        return Promise.reject(error);
      }

      store.commit("setRefreshing", true);

      try {
        const res = await axios.post("/auth/refresh-token", {
          refresh_token: refreshToken
        });

        const { access_token, refresh_token: newRefresh, expires_in } = res.data;

        localStorage.setItem("access_token", access_token);
        localStorage.setItem("refresh_token", newRefresh);
        localStorage.setItem("expires_in", expires_in);

        // ✅ Set header axios
        axios.defaults.headers.common["Authorization"] = `Bearer ${access_token}`;

        // ✅ Update state token di Vuex
        store.commit("retrieveToken", access_token);
        store.commit("setRefreshing", false);

        // Retry ulang request yang error 401
        error.config.headers = error.config.headers || {};
        error.config.headers["Authorization"] = `Bearer ${access_token}`;
        return axios(error.config);

      } catch (refreshErr) {
        store.commit("setRefreshing", false);
        localStorage.clear();
        window.location.href = "/login";
        return Promise.reject(refreshErr);
      }
    }

    return Promise.reject(error);
  }
)

Vue.use(GmapVue, {
  load: {
    key: process.env.VUE_APP_GOOGLE_MAPS_KEY || 'AIzaSyCV2s4dDQivQr1rp8dC0f4BE1kG5Qh7zJ0',
    libraries: 'places,drawing', // This is required if you use the Autocomplete plugin
    // OR: libraries: 'places,drawing'
    // OR: libraries: 'places,drawing,visualization'
    // (as you require)

    //// If you want to set the version, you can do so:
    // v: '3.26',
  },
  //// If you intend to programmatically custom event listener code
  //// (e.g. `this.$refs.gmap.$on('zoom_changed', someFunc)`)
  //// instead of going through Vue templates (e.g. `<GmapMap @zoom_changed="someFunc">`)
  //// you might need to turn this on.
  // autobindAllEvents: false,

  //// If you want to manually install components, e.g.
  //// import {GmapMarker} from 'vue2-google-maps/src/components/marker'
  //// Vue.component('GmapMarker', GmapMarker)
  //// then set installComponents to 'false'.
  //// If you want to automatically install all the components this property must be set to 'true':
  installComponents: true
})

// plugin setup
Vue.use(Global);
Vue.use(DashboardPlugin);
Vue.use(VueRouter);
Vue.use(RouterPrefetch);
Vue.use(VueTour);
Vue.prototype.$redirectToAddIntegration = redirectToAddIntegration;
// Attach moment-timezone to Vue's prototype
Vue.prototype.$moment = moment;
const clientTimezone = moment.tz.guess();

moment.tz.setDefault(clientTimezone); // Set a default timezone if needed

// Set the source and target timezones
const sourceTimezone = clientTimezone; // Example: New York timezone
const targetTimezone = 'America/New_York'; // Example: Los Angeles timezone

// Parse the input time in the source timezone
const sourceMoment = moment.tz("2023-09-07 00:00:00", sourceTimezone);

// Convert the time to the target timezone
const targetMoment = sourceMoment.clone().tz(targetTimezone);
//console.log(moment("2023-09-06 21:40:00").format('YYYY-MM-DD HH:mm:ss'));
//console.log(targetMoment.format('YYYY-MM-DD HH:mm:ss'));
//console.log(clientTimezone);

// check beta
async function checkBetaFeatureAgreement(to, from, next, store, global) {
  const b2bUrl = typeof(store.getters.userData.leadb2burl) != 'undefined' ? store.getters.userData.leadb2burl : null;
  const b2bPath = b2bUrl ? `/${b2bUrl}/campaign-management` : '';

  const simplifiUrl = typeof(store.getters.userData.leadsimplifiurl) != 'undefined' ? store.getters.userData.leadsimplifiurl : null;
  const simplifiPath = simplifiUrl ? `/${simplifiUrl}/campaign-management` : '';
  
  const cleanPath = '/cleanid';

  if(
    (b2bUrl && to.path == b2bPath && global.betaFeature.b2b_module.is_beta && (global.betaFeature.b2b_module.apply_to_all_agency || global.isBeta)) || // untuk beta b2b
    (simplifiUrl && to.path == simplifiPath && global.betaFeature.simplifi_module.is_beta && (global.betaFeature.simplifi_module.apply_to_all_agency || global.isBeta)) || // untuk beta simplifi
    (cleanPath && to.path == cleanPath && global.betaFeature.clean_module.is_beta && (global.betaFeature.clean_module.apply_to_all_agency || global.isBeta)) // untuk beta clean
  ) {
    try {
      const pathToFeatureMap = {
        [b2bPath]: "b2b_module",
        [simplifiPath]: "simplifi_module",
        [cleanPath]: "clean_module"
      };
      let featureType = pathToFeatureMap[to.path];

      const userData = store.getters.userData;
      const response2 = await store.dispatch('getMarketingServicesAgreementFeature', {
        user_id: userData.id,
        feature_id: global.betaFeature[featureType].id
      })

      if(response2.status != 'T') {
        const result = await swal.fire({
          title: 'Service Agreement',
          html: `
            <p class="swal-agreement-feature-text">
              I understand that any features marked as beta in the system are under development 
              and not released for commercial use. Any commercial use of these features 
              are undertaken by the client for their use and are not warranted against errors.
            </p>
          `,
          icon: '',
          confirmButtonText: 'Yes, I Agree',
          showCloseButton: true,
          customClass: {
            popup: 'swal-agreement-feature-popup',
            header: 'swal-agreement-feature-header',
            title: 'swal-agreement-feature-title',
            confirmButton: 'swal-agreement-feature-confirm-button',
          }
        });        

        if(result.isConfirmed) {
          try {
            const response3 = await store.dispatch('marketingServicesAgreementFeature', {
              feature_id: global.betaFeature[featureType].id,
              feature_name: global.betaFeature[featureType].name,
              user_id: userData.id,
              is_marketing_services_agreement_feature: 'T',
              company_name: userData.company_name,
              company_id: userData.company_id,
              location_menu: to.name,
            })
          } catch (error) {
            Vue.prototype.$notify({
              type: 'danger',
              message: 'Something went wrong, please try again later',
              icon: 'fas fa-bug'
            })
            console.error(error);
            next(from.fullPath);
          }
        } else {
          next(from.fullPath);
        }
      }
    } catch (error) {
      console.error(error);
    }
  }
}
// check beta

// check data wallet agency has ben aggree
function checkAgencyDataWalletAgreement(to, from, next, store, global, isDataWalletAgree) {
  // console.log({
  //   'global.systemUser': global.systemUser,
  //   'global.idsys': global.idsys,
  //   'global.masteridsys': global.masteridsys,
  //   'global.menuUserType': global.menuUserType,
  //   'global.betaFeature.data_wallet.apply_to_all_agency': global.betaFeature.data_wallet.apply_to_all_agency,
  //   'global.isBeta': global.isBeta,
  //   'isDataWalletAgree': isDataWalletAgree,
  //   'global.creditcardsetup': global.creditcardsetup,
  //   'global.menuLeadsPeek': global.menuLeadsPeek,
  // })
  global.modals.billingAgreementDirectPayment = false;
  if (
    !global.systemUser && // hanya untuk agency bukan root
    global.idsys == global.masteridsys && // jika agency dibawah root emm
    ['user', 'userdownline'].includes(global.menuUserType) && // jika usertype nya user atau userdowline
    global.betaFeature.data_wallet.is_beta && // jika data wallet beta
    (global.betaFeature.data_wallet.apply_to_all_agency || global.isBeta) && // jika data wallet apply to all atau jika user ini beta 
    !isDataWalletAgree && // jika user is belom aggree data wallet
    global.creditcardsetup // jika agency sudah mengisi credit card
  ) {
    global.modals.billingAgreementDirectPayment = true;
  }
}
// check data wallet agency has ben aggree


router.beforeEach(async (to, from, next) => {
  $('head').find('style#cssCustomAgency').remove();
  $('head').find('style#cssGlobalTextColor').remove();
  $('head').find('style#cssGlobalLinkColor').remove();
  
  if (to.matched.some(record => record.meta.requiresAuth)) {
    if (!store.getters.loggedIn) {
      next({
        name: 'Login',
      })
    }else{
      /** VALIDATE MODULE AUTHORIZED */
      var global = Vue.prototype.$global
      // var user = store.getters.userData
      var user = store.getters.userData;
      var companyID = user['company_id'];
      var roleid = user['role_id'];
      var userType = user['user_type'];
      var systemuser = user['systemuser'];
      var isAccExecutive = user['isAccExecutive'];
      var userTypeOri = user['user_type_ori'];
      global.menuUserType = userType;
      global.systemUser = systemuser;
      global.isAccExecutive = isAccExecutive;
      global.userTypeOri = userTypeOri;
      var menuEnabled = to.meta.menuEnabled
      
      if (typeof(userType) != 'undefined') {
        if(userType == 'client') {
          if (global.clientPaymentFailed) {
            next();
          }
        }
      }

      /** CHECK FOR  VIEW MODE AS ANOTHER USER*/
      if (localStorage.getItem('userDataOri') === null) {
        global.globalviewmode = false;
      }else{
        global.globalviewmode = true;
      }
      /** CHECK FOR  VIEW MODE AS ANOTHER USER*/

      /** CHECK FOR VIEW MODE AS CLIENT */
      global.globalviewmodeclient = (localStorage.getItem('userDataAgency') !== null);
      /** CHECK FOR VIEW MODE AS CLIENT */

      /** CHECK FOR DENY PERMISSIONS*/
      global.user_permissions = null
      if(user.user_permissions){
        const userPermissions = JSON.parse(user.user_permissions)

        global.user_permissions = userPermissions
      }
      /** CHECK FOR DENY PERMISSIONS*/

      /** CHECK FOR DENY PERMISSIONS VIEW MODE*/
      if(user.user_permissions_view_mode){
        const userPermissionsViewMode = user.user_permissions_view_mode

        global.user_permissions = userPermissionsViewMode
      }
      /** CHECK FOR DENY PERMISSIONS VIEW MODE*/

      /** CHECK FOR SYS ID */
      if (global.idsys == "" || global.companyrootname == "") {
        let domainorsub = ""
        
        if(localStorage.getItem('subdomainAgency') && localStorage.getItem('subdomainAgency') != 'undefined') {
          domainorsub = localStorage.getItem('subdomainAgency');
        }

        else {
          domainorsub = window.location.hostname;
          localStorage.removeItem('subdomainAgency')
        }
        global.isLoadingSystem = false
        try {
          global.isLoadingSystem = true
          let agencyCompanyId = null;
          try {
            const currentUser = store.getters.userData;
            if (currentUser) {
              if (currentUser.user_type === 'client' && currentUser.company_parent) {
                agencyCompanyId = currentUser.company_parent;
              }
              else if (currentUser.user_type === 'userdownline' && currentUser.company_id) {
                agencyCompanyId = currentUser.company_id;
              }
            }
          } catch (e) {
            // Fail-safe: do not block flow if we cannot determine agencyCompanyId
            agencyCompanyId = null;
          }

          const response = await store.dispatch('getDomainorSubInfo', {
            domainorsub: domainorsub,
            agencyCompanyId: agencyCompanyId,
          });

          global.idsys = response.params.idsys;    
          global.masteridsys = response.params.masteridsys;
          global.companyrootname = response.params.companyrootname;
          global.companyrootlegalname = response.params.companyrootlegalname;
          global.companyrootnameshort = response.params.companyrootnameshort;
          global.companyrootaddres = response.params.companyrootaddres;
          global.companyrootcity = response.params.companyrootcity;
          global.companyrootzip = response.params.companyrootzip;
          global.companyrootstatecode = response.params.companyrootstatecode;
          global.companyrootstatename = response.params.companyrootstatename;
          global.companyrootdomain = response.params.companyrootdomain;
          global.companyrootsubdomain = response.params.companyrootsubdomain;
          global.companyrootphone = response.params.companyrootphone;
          global.companyrootagreementattention = response.params.companyrootagreementattention;
          global.companyrootagreementemail = response.params.companyrootagreementemail;

          global.userrootname = response.params.userrootname;
          global.userrootemail = response.params.userrootemail;
          global.userrootaddres = response.params.userrootaddres;
          global.userrootcity = response.params.userrootcity;
          global.userrootzip = response.params.userrootzip;
          global.userrootstatecode = response.params.userrootstatecode;
          global.userrootstatename = response.params.userrootstatename;
          global.sppubkey = response.params.sppubkey;
          global.recapkey = response.params.recapkey;
          global.charges_enabled = response.params.charges_enabled;
          global.payouts_enabled = response.params.payouts_enabled;
          global.account_requirements = response.params.account_requirements;
          global.hasVisibleIntegrations = response.params.hasVisibleIntegrations !== undefined ? response.params.hasVisibleIntegrations : true;
          if (response.params.rootcomp == "T") {
            global.rootcomp = true;
          }else{
            global.rootcomp = false;
            if(localStorage.getItem('rootcomp') == 'true') {
              global.rootcomp = true;
            }
          }

          if(global.rootcomp){
            const updatedData = {
              rootcomp: global.rootcomp
            }
            
            store.dispatch('updateUserData', updatedData);
          }

          if (response.params.agencyplatformroot == 'T') {
            global.agencyplatformroot = true;
          }else{
            global.agencyplatformroot = false;
          }

          /** CHECK FOR STATUS DOMAIN */
          const updatedData = {
            status_domain: response.params.status_domain
          }
          
          store.dispatch('updateUserData', updatedData);
          /** CHECK FOR STATUS DOMAIN */
          
          global.rootpaymentterm = response.paymenttermlist;

          global.rootcustomsidebarleadmenu = response.rootsidemenu;
          global.customsidebarleadmenu = response.sidemenu;
          global.agencysidebar = response.agencysidebar;
          // console.log({'response.agencysidebar': response.agencysidebar})
          const menuItems = ['local', 'locator', 'enhance', 'b2b', 'simplifi'];
          if (global.agencysidebarsetting !== '') {
            menuItems.forEach(item => {
              global.agencysidebar[item] = typeof response.agencysidebar[item] === 'undefined' || typeof response.agencysidebar[item] === null  ? false : true;
            });
          }           

        } 
        catch (error) {
          console.error(error);
        } finally {
          global.isLoadingSystem = false
        }
      }
      /** CHECK FOR SYS ID */

      /** SETUP GLOBAL THINGS */
      if (user.user_type == 'client' && (user.company_name == '' || user.company_name == null)) {
        global.globalCompanyName = user.companyparentname; 
      }else{
        global.globalCompanyName = user.company_name;
      }
      document.title = user.company_name;
      if(user.company_logo != null && user.company_logo != '') {
        global.globalCompanyPhoto = user.company_logo;
        $('link[rel="icon"]').attr('href', user.company_logo);
        $('link[rel="apple-touch-icon"]').attr('href', user.company_logo);
      }else{
        if (user.user_type == 'client' && user.companyparentlogo != null && user.companyparentlogo != '') {
          global.globalCompanyPhoto = user.companyparentlogo;
          $('link[rel="icon"]').attr('href', user.companyparentlogo);
          $('link[rel="apple-touch-icon"]').attr('href', user.companyparentlogo);
        }else{
          global.globalCompanyPhoto = '/img/logoplaceholder.png'
          $('link[rel="icon"]').attr('href', '/favicon.png');
          $('link[rel="apple-touch-icon"]').attr('href', '/favicon.png');
        }
      }
      if(user.profile_pict != null && user.profile_pict != '') {
        global.globalProfilePhoto = user.profile_pict;
      }else{
        global.globalProfilePhoto = '/img/placeholder.jpg'
      } 
      /** SETUP GLOBAL THINGS */

      /** SETUP CUSTOM THEME */
      
      global.globalBoxBgColor = user.box_bgcolor;
      global.globalTextColor = user.text_color;
      global.globalLinkColor = user.link_color;
      
      global.globalTemplateBgColor = user.template_bgcolor;
      global.globalSidebarBgColor = user.sidebar_bgcolor;
      global.globalFontTheme = user.font_theme;
      
      let getParentsColor = localStorage.getItem('parentsColor')
      if(getParentsColor){
        getParentsColor = JSON.parse(getParentsColor)

        global.globalSidebarBgColor = getParentsColor.sidebar_bgcolor;
        global.globalTextColor = getParentsColor.text_color;
      }


      $('head').append('<style id="cssCustomAgency">' +
        '.sidebar:before{border-bottom-color:' +  global.globalSidebarBgColor + ' !important;} ' +
        // '.clickable-rows .el-table, .el-table__expanded-cell {background-color:' + global.globalBoxBgColor + ' !important;} ' +
        // '.label-border-box {background-color:' + global.globalBoxBgColor + ' !important;} ' +
        // '.form-control[disabled] {background-color: rgba(0, 0, 0, 0);color: rgba(255, 255, 255, 0.2);border-color:#2b3553}' +
        // '.input-group-prepend .input-group-text, .input-group-append .input-group-text {border-color:' + global.globalTextColor + '} ' +
        // '.form-control {border-color:' + global.globalTextColor + '} ' +
        '</style>');
      $('body').css('font-family',global.globalFontTheme);
      
      if (to.name != "Login" && to.name != "Register" && to.name != "Agency Register" && to.name != "TermUse" && to.name != "PrivacyPolicy") {
        $('head').append('<style id="cssGlobalTextColor">#sidebarCompanyName, .sidebar-item-wrapper {color:' +  global.globalTextColor + ' !important;}</style>');
        // $('head').append('<style id="cssGlobalLinkColor">body a, a span {color:' +  global.globalLinkColor + ' !important;}</style>');
      }

      /** SETUP CUSTOM THEME */

      /** SETUP CUSTOM SIDEMENU */
      if(user.leadlocalname != null && user.leadlocalname != '') {
        global.globalModulNameLink.local.name = user.leadlocalname;
      }
      if(user.leadlocalurl != null && user.leadlocalurl != '') {
        global.globalModulNameLink.local.url = user.leadlocalurl;
      }
      if(user.leadlocatorname != null && user.leadlocatorname != '') {
        global.globalModulNameLink.locator.name = user.leadlocatorname;
      }
      if(user.leadlocatorurl != null && user.leadlocatorurl != '') {
        global.globalModulNameLink.locator.url = user.leadlocatorurl;
      }
      global.globalModulNameLink.enhance.name = user.leadenhancename;
      global.globalModulNameLink.enhance.url = user.leadenhanceurl;
      global.globalModulNameLink.b2b.name = user.leadb2bname;
      global.globalModulNameLink.b2b.url = user.leadb2burl;
      global.globalModulNameLink.simplifi.name = user.leadsimplifiname;
      global.globalModulNameLink.simplifi.url = user.leadsimplifiurl;
      /** SETUP CUSTOM SIDEMENU */

      global.isLoadingContent = false;

      if(userType == 'client' && global.clientPaymentFailed == false) {
        global.isLoadingContent = true;
        /** CHECK FOR CLIENT DISABLED MENU CAMPAIGN MANAGEMENT */
        store.dispatch('getUserData',{
            usrID: 'only/' + user.id,
        }).then(response => {
            if (response.disable_client_add_campaign == 'T') {
              global.disabledaddcampaign = false;
              if (typeof(to.meta.itemname) != 'undefined' && to.meta.itemname == 'campaignmanagement') {
                next({
                  name: 'Dashboard',
                })
              }
            }else{
              global.disabledaddcampaign = true;
            }
        },error => {
            
        });
        /** CHECK FOR CLIENT DISABLED MENU CAMPAIGN MANAGEMENT */
        store.dispatch('checkUserSetupComplete', {
            usrID: user['id'],
        }).then(async response => {
            // beta feature
            global.isBeta = response.isBeta;
            global.betaFeature = response.betaFeature;
            global.isLoadingContent = false;
            await checkBetaFeatureAgreement(to, from, next, store, global);
            // beta feature
            
            global.paymentStatusFailed = response.paymentStatusFailed;
            global.checkClientModule(response.setupcomplete,response.accessmodule);

            /* FOR SIDEBAR ENHANCE */
            if(typeof(response.rootsidemenu.enhance) === 'undefined' || typeof(response.rootsidemenu.enhance) === null) {
              let userData = store.getters.userData;
              
              // overwrite leadenhancename and leadenhanceurl in localstorage
              if(userData.leadenhancename != null || userData.leadenhanceurl != null) {
                userData.leadenhancename = null;
                userData.leadenhanceurl = null;

                global.globalModulNameLink.enhance.name = userData.leadenhancename;
                global.globalModulNameLink.enhance.url = userData.leadenhanceurl;
                
                // update userData In LocalStorage
                const updatedData = {
                  leadenhancename: userData.leadenhancename,
                  leadenhanceurl: userData.leadenhanceurl,
                }

                store.dispatch('updateUserData', updatedData);
                // update store.getters.userData to sync with localStorage
                store.dispatch('fetchUserFromLocalStorage');
                // reload the page to update router enhance
                window.location.href = '/';
              }
            } else {
              let userData = store.getters.userData;
              
              // overwrite leadenhancename and leadenhanceurl in localstorage
              if(userData.leadenhancename == null || userData.leadenhanceurl == null) {
                userData.leadenhancename = (typeof(response.sidemenu.enhance) !== 'undefined') ? response.sidemenu.enhance.name : response.rootsidemenu.enhance.name;
                userData.leadenhanceurl = (typeof(response.sidemenu.enhance) !== 'undefined') ? response.sidemenu.enhance.url : response.rootsidemenu.enhance.url;

                global.globalModulNameLink.enhance.name = userData.leadenhancename;
                global.globalModulNameLink.enhance.url = userData.leadenhanceurl;

                // update userData In LocalStorage
                const updatedData = {
                  leadenhancename: userData.leadenhancename,
                  leadenhanceurl: userData.leadenhanceurl,
                }

                store.dispatch('updateUserData', updatedData);
                // update store.getters.userData to sync with localStorage
                store.dispatch('fetchUserFromLocalStorage');
                // reload the page to update router enhance
                window.location.href = '/';
              }
            }
            /* FOR SIDEBAR ENHANCE */

            /* FOR SIDEBAR B2B */
            if(typeof(response.rootsidemenu.b2b) === 'undefined' || typeof(response.rootsidemenu.b2b) === null) {
              let userData = store.getters.userData;
              
              // overwrite leadb2bname and leadb2burl in localstorage
              if(userData.leadb2bname != null || userData.leadb2burl != null) {
                userData.leadb2bname = null;
                userData.leadb2burl = null;

                global.globalModulNameLink.b2b.name = userData.leadb2bname;
                global.globalModulNameLink.b2b.url = userData.leadb2burl;
                
                // update userData In LocalStorage
                const updatedData = {
                  leadb2bname: userData.leadb2bname,
                  leadb2burl: userData.leadb2burl,
                }

                store.dispatch('updateUserData', updatedData);
                // update store.getters.userData to sync with localStorage
                store.dispatch('fetchUserFromLocalStorage');
                // reload the page to update router b2b
                window.location.href = '/';
              }
            } else {
              let userData = store.getters.userData;
              
              // overwrite leadb2bname and leadb2burl in localstorage
              if(userData.leadb2bname == null || userData.leadb2burl == null) {
                userData.leadb2bname = (typeof(response.sidemenu.b2b) !== 'undefined') ? response.sidemenu.b2b.name : response.rootsidemenu.b2b.name;
                userData.leadb2burl = (typeof(response.sidemenu.b2b) !== 'undefined') ? response.sidemenu.b2b.url : response.rootsidemenu.b2b.url;

                global.globalModulNameLink.b2b.name = userData.leadb2bname;
                global.globalModulNameLink.b2b.url = userData.leadb2burl;

                // update userData In LocalStorage
                const updatedData = {
                  leadb2bname: userData.leadb2bname,
                  leadb2burl: userData.leadb2burl,
                }

                store.dispatch('updateUserData', updatedData);
                // update store.getters.userData to sync with localStorage
                store.dispatch('fetchUserFromLocalStorage');
                // reload the page to update router b2b
                window.location.href = '/';
              }
            }
            /* FOR SIDEBAR B2B */

            /* FOR SIDEBAR SIMPLIFI ONLY EMM */
            if(typeof(response.rootsidemenu.simplifi) === 'undefined') {
              let userData = store.getters.userData;
              
              // overwrite leadsimplifiname and leadsimplifiurl in localstorage
              if(userData.leadsimplifiname != null || userData.leadsimplifiurl != null) {
                userData.leadsimplifiname = null;
                userData.leadsimplifiurl = null;

                global.globalModulNameLink.simplifi.name = userData.leadsimplifiname;
                global.globalModulNameLink.simplifi.url = userData.leadsimplifiurl;

                // update userData In LocalStorage
                const updatedData = {
                  leadsimplifiname: userData.leadsimplifiname,
                  leadsimplifiurl: userData.leadsimplifiurl,
                }

                store.dispatch('updateUserData', updatedData);
                // update store.getters.userData to sync with localStorage
                store.dispatch('fetchUserFromLocalStorage');
                // reload the page to update router b2b
                window.location.href = '/';
              }
            } else {
              let userData = store.getters.userData;
              
              // overwrite leadsimplifiname and leadsimplifiurl in localstorage
              if(userData.leadsimplifiname == null || userData.leadsimplifiurl == null) {
                userData.leadsimplifiname = (typeof(response.sidemenu.simplifi) !== 'undefined') ? response.sidemenu.simplifi.name : response.rootsidemenu.simplifi.name;
                userData.leadsimplifiurl = (typeof(response.sidemenu.simplifi) !== 'undefined') ? response.sidemenu.simplifi.url : response.rootsidemenu.simplifi.url;

                global.globalModulNameLink.simplifi.name = userData.leadsimplifiname;
                global.globalModulNameLink.simplifi.url = userData.leadsimplifiurl;

                // update userData In LocalStorage
                const updatedData = {
                  leadsimplifiname: userData.leadsimplifiname,
                  leadsimplifiurl: userData.leadsimplifiurl,
                }

                store.dispatch('updateUserData', updatedData);
                // update store.getters.userData to sync with localStorage
                store.dispatch('fetchUserFromLocalStorage');
                // reload the page to update router b2b
                window.location.href = '/';
              }
            }
            /* FOR SIDEBAR SIMPLIFI ONLY EMM */

            /* FOR CHECK CONNECTED ACCOUNT AGENCY */
            global.statusaccountconnected = response.accountconnected;
            if (response.accountconnected == 'completed' && response.package_id != '') {
              // console.log('block 1');
              global.stripeaccountconnected = true;
            }else if (response.accountconnected == '' && response.paymentgateway != 'stripe' && response.package_id != '') {
              // console.log('block 2');
              global.stripeaccountconnected = true;
            }else if (response.accountconnected == '' && response.paymentgateway == 'stripe' && (response.package_id != '' && user.manual_bill == 'T')) {
              // console.log('block 3');
              global.stripeaccountconnected = true;
            }else if (response.accountconnected == 'failed' && response.paymentgateway == 'stripe' && (response.package_id != '' && user.manual_bill == 'T')) {
              // console.log('block 4');
              global.stripeaccountconnected = true;
            }else{
              // console.log('block 5');
              global.stripeaccountconnected = false;

              if (['', 'pending', 'inverification'].includes(response.accountconnected)) {
                global.statusaccountconnected = 'Your agency connected Stripe account is not yet fully set up. To proceed, please contact your agency administrator to complete the setup process';
              }

              if (!window.hasShownAccountConnectNotifyInClient && response.accountconnected != 'completed' && user.manual_bill == 'F') {
                window.hasShownAccountConnectNotifyInClient = true;
                Vue.prototype.$notify({
                  id:'popstatusaccountconnect',
                  message: global.statusaccountconnected,
                  timeout: 0,
                  icon: 'fas fa-megaphone',
                  horizontalAlign: 'right',
                  verticalAlign: 'top',
                  type: 'danger',
                  ignoreDuplicates: true,
                });
              }
            }
            /* FOR CHECK CONNECTED ACCOUNT AGENCY */

            if (typeof to.meta.menuname != 'undefined') {
              if (to.meta.menuname == 'menuAdsDesign') {
                menuEnabled = Vue.prototype.$global.menuAdsDesign
              }else if (to.meta.menuname == 'menuCampaign') {
                menuEnabled = Vue.prototype.$global.menuCampaign
              }else if (to.meta.menuname == 'menuLeadsPeek') {
                menuEnabled = Vue.prototype.$global.menuLeadsPeek
              }else if (to.meta.menuname == 'settingMenuShow') {
                menuEnabled = Vue.prototype.$global.settingMenuShow
              }
            }

            if (user.customer_card_id != '') {
              global.creditcardsetup = true;

              if(global.agency_onboarding_status && response.setupcomplete == 'F'){
                global.creditcardsetup = false;
              }
            }else{
              global.creditcardsetup = false;
            }

            if (user.questionnaire_setup_completed == 'T') {
              global.questionnairesetup = true;
            }else{
              global.questionnairesetup = false;
            }
            
            if(to.name == 'Card Setting' && (user.customer_card_id == '' || response.setupcomplete == 'F')) {
              //console.log('A');
              next({
                name: 'Profile Setup',
              })
            }else if (menuEnabled && to.meta.clientTypeAccess.includes(store.getters.getUserType)) {
              next()
            }else{
              //console.log('B');
              next({
                name: 'Profile Setup',
              })
            }

            global.clientPaymentFailed = false;
            
            if (response.setupcomplete == 'F' && response.accessmodule == 'paymentfailed') {
              global.clientPaymentFailed = true;
              global.failedCampaignNumber = response.fcampid;
              global.failedInvoiceAmount = response.finamt;
              
              next({
                name: 'Card Setting',
              })
            }

            // global.clientsidebar = [];
            // if (response.clientsidebar != []) {
            //   global.clientsidebar = response.clientsidebar
            // }
            global.clientsidebar = response.clientsidebar
            // console.log({'response.clientsidebar': response.clientsidebar})
            const menuItems = ['local', 'locator', 'enhance', 'b2b', 'simplifi'];
            if (global.clientsidebar != []) {
              menuItems.forEach(item => {
                global.clientsidebar[item] = typeof response.clientsidebar[item] == 'undefined' || typeof response.clientsidebar[item] === null ? false : true;
              });
            }
        },error => {
          console.log('Token expired');
          localStorage.removeItem('access_token');
          localStorage.removeItem('userData');
          localStorage.removeItem('userRole');
          document.location = "/";
          global.isLoadingContent = false;
        });
      
      }else{
        global.isLoadingContent = true;

        store.dispatch('GetRoleList', {
              companyID: companyID,
              getType:'getrolemodule',
              roleID:roleid,
              usrID: user['id'],
          }).then(async response => {

              localStorage.setItem('is_master_company', response.is_master_company === true ? 'true' : 'false');
              // beta feature 
              global.isBeta = response.isBeta;
              global.betaFeature = response.betaFeature;
              global.isLoadingContent = false;
              await checkBetaFeatureAgreement(to, from, next, store, global);
              // beta feature 

              global.agency_onboarding_status = response.agency_onboarding_status
              global.agency_onboarding_price = response.agency_onboarding_price
              global.agency_enable_coupon = response.agency_enable_coupon
              global.rootminspendsetting = response.minspend_setting

              global.apiMode = response.api_mode
              global.is_marketing_services_agreement_developer = response.is_marketing_services_agreement_developer

              // Store enabled_client_deleted_account from agency owner
              if (response.enabled_client_deleted_account !== undefined) {
                global.enabled_client_deleted_account = response.enabled_client_deleted_account
              }

              global.paymentStatusFailed = response.paymentStatusFailed;
              global.agencyPaymentFailed = false;
              if ((global.menuUserType == 'userdownline' || global.menuUserType == 'user') && response.accessmodule_agency == 'paymentfailed') {
                global.agencyPaymentFailed = true;
                global.failedCampaignNumber = response.fcampid;
                global.failedInvoiceAmount = response.finamt;
                // console.log('bbb menuUserType ' + global.menuUserType);
                // console.log('bbb accessmodule_agency ' + response.accessmodule_agency);
                // console.log('bbb agencyPaymentFailed ' + global.agencyPaymentFailed);
                // console.log('bbb failedCampaignNumber ' + global.failedCampaignNumber);
                // console.log('bbb failedInvoiceAmount ' + global.failedInvoiceAmount);
                if(to.name != 'Card Setting' && !global.globalviewmode) {
                  next({
                    name: 'Card Setting',
                  })
                }
              }

              //this.rolemodulelist = response;
              global.agencyfilteredmodules = response.agencyFilteredModules;
              global.agency_side_menu = response.agency_side_menu
              // console.log({'global.agencyfilteredmodules': global.agencyfilteredmodules, 'global.agency_side_menu': global.agency_side_menu})
              // console.log({'action': 'getrolelist','typeof_rootsidemenu_b2b': typeof(response.rootsidemenu.b2b),'response.agency_side_menu': response.agency_side_menu});

              /* FOR SIDEBAR ENHANCE */
              if(typeof(response.rootsidemenu.enhance) === 'undefined') {
                let userData = store.getters.userData;
                
                // overwrite leadenhancename and leadenhanceurl in localstorage
                if(userData.leadenhancename != null || userData.leadenhanceurl != null) {          
                  userData.leadenhancename = null;
                  userData.leadenhanceurl = null;

                  global.globalModulNameLink.enhance.name = userData.leadenhancename;
                  global.globalModulNameLink.enhance.url = userData.leadenhanceurl;

                  // update userData In LocalStorage
                  const updatedData = {
                    leadenhancename: userData.leadenhancename,
                    leadenhanceurl: userData.leadenhanceurl,
                  }
  
                  store.dispatch('updateUserData', updatedData);
                  // update store.getters.userData to sync with localStorage
                  store.dispatch('fetchUserFromLocalStorage');
                  // reload the page to update router enhance
                  window.location.href = '/';
                }
              } else {
                let userData = store.getters.userData;

                // overwrite leadenhancename and leadenhanceurl in localstorage
                if(userData.leadenhancename == null || userData.leadenhanceurl == null) {
                  userData.leadenhancename = (typeof(response.sidemenu.enhance) !== 'undefined') ? response.sidemenu.enhance.name : response.rootsidemenu.enhance.name;
                  userData.leadenhanceurl = (typeof(response.sidemenu.enhance) !== 'undefined') ? response.sidemenu.enhance.url : response.rootsidemenu.enhance.url;

                  global.globalModulNameLink.enhance.name = userData.leadenhancename;
                  global.globalModulNameLink.enhance.url = userData.leadenhanceurl;

                  // update userData In LocalStorage
                  const updatedData = {
                    leadenhancename: userData.leadenhancename,
                    leadenhanceurl: userData.leadenhanceurl,
                  }
  
                  store.dispatch('updateUserData', updatedData);
                  // update store.getters.userData to sync with localStorage
                  store.dispatch('fetchUserFromLocalStorage');
                  // reload the page to update router enhance
                  window.location.href = '/';
                }
              }
              /* FOR SIDEBAR ENHANCE */

              /* FOR SIDEBAR B2B */
              if(typeof(response.rootsidemenu.b2b) === 'undefined') {
                let userData = store.getters.userData;
                
                // overwrite leadb2bname and leadb2burl in localstorage
                if(userData.leadb2bname != null || userData.leadb2burl != null) {
                  userData.leadb2bname = null;
                  userData.leadb2burl = null;

                  global.globalModulNameLink.b2b.name = userData.leadb2bname;
                  global.globalModulNameLink.b2b.url = userData.leadb2burl;

                  // update userData In LocalStorage
                  const updatedData = {
                    leadb2bname: userData.leadb2bname,
                    leadb2burl: userData.leadb2burl,
                  }
  
                  store.dispatch('updateUserData', updatedData);
                  // update store.getters.userData to sync with localStorage
                  store.dispatch('fetchUserFromLocalStorage');
                  // reload the page to update router b2b
                  window.location.href = '/';
                }
              } else {
                let userData = store.getters.userData;
                
                // overwrite leadb2bname and leadb2burl in localstorage
                if(userData.leadb2bname == null || userData.leadb2burl == null) {
                  userData.leadb2bname = (typeof(response.sidemenu.b2b) !== 'undefined') ? response.sidemenu.b2b.name : response.rootsidemenu.b2b.name;
                  userData.leadb2burl = (typeof(response.sidemenu.b2b) !== 'undefined') ? response.sidemenu.b2b.url : response.rootsidemenu.b2b.url;

                  global.globalModulNameLink.b2b.name = userData.leadb2bname;
                  global.globalModulNameLink.b2b.url = userData.leadb2burl;

                  // update userData In LocalStorage
                  const updatedData = {
                    leadb2bname: userData.leadb2bname,
                    leadb2burl: userData.leadb2burl,
                  }
  
                  store.dispatch('updateUserData', updatedData);
                  // update store.getters.userData to sync with localStorage
                  store.dispatch('fetchUserFromLocalStorage');
                  // reload the page to update router b2b
                  window.location.href = '/';
                }
              }
              /* FOR SIDEBAR B2B */

              /* FOR SIDEBAR SIMPLIFI ONLY EMM */
              if(typeof(response.rootsidemenu.simplifi) === 'undefined') {
                let userData = store.getters.userData;
                
                // overwrite leadsimplifiname and leadsimplifiurl in localstorage
                if(userData.leadsimplifiname != null || userData.leadsimplifiurl != null) {
                  userData.leadsimplifiname = null;
                  userData.leadsimplifiurl = null;

                  global.globalModulNameLink.simplifi.name = userData.leadsimplifiname;
                  global.globalModulNameLink.simplifi.url = userData.leadsimplifiurl;

                  // update userData In LocalStorage
                  const updatedData = {
                    leadsimplifiname: userData.leadsimplifiname,
                    leadsimplifiurl: userData.leadsimplifiurl,
                  }
  
                  store.dispatch('updateUserData', updatedData);
                  // update store.getters.userData to sync with localStorage
                  store.dispatch('fetchUserFromLocalStorage');
                  // reload the page to update router b2b
                  window.location.href = '/';
                }
              } else {
                let userData = store.getters.userData;
                
                // overwrite leadsimplifiname and leadsimplifiurl in localstorage
                if((userData.leadsimplifiname == null || userData.leadsimplifiurl == null)) {
                  userData.leadsimplifiname = (typeof(response.sidemenu.simplifi) !== 'undefined') ? response.sidemenu.simplifi.name : response.rootsidemenu.simplifi.name;
                  userData.leadsimplifiurl = (typeof(response.sidemenu.simplifi) !== 'undefined') ? response.sidemenu.simplifi.url : response.rootsidemenu.simplifi.url;

                  global.globalModulNameLink.simplifi.name = userData.leadsimplifiname;
                  global.globalModulNameLink.simplifi.url = userData.leadsimplifiurl;
                  // update userData In LocalStorage
                  const updatedData = {
                    leadsimplifiname: userData.leadsimplifiname,
                    leadsimplifiurl: userData.leadsimplifiurl,
                  }
  
                  store.dispatch('updateUserData', updatedData);
                  // update store.getters.userData to sync with localStorage
                  store.dispatch('fetchUserFromLocalStorage');
                  // reload the page to update router simplifi
                  window.location.href = '/';
                }
              }
              /* FOR SIDEBAR SIMPLIFI ONLY EMM */

              if(!global.systemUser){
                if(response.is_whitelabeling == 'F'){
                  const parentsColor = response.colors_parent
                  global.globalTextColor = response.colors_parent.text_color;
                  global.globalSidebarBgColor = response.colors_parent.sidebar_bgcolor;
                  document.documentElement.style.setProperty('--bg-bar-color', response.colors_parent.sidebar_bgcolor);
                  document.documentElement.style.setProperty('--text-bar-color', response.colors_parent.text_color);

                  localStorage.setItem('parentsColor', JSON.stringify(parentsColor))
                } else {
                  localStorage.removeItem('parentsColor')
                }
              }

              if(response.paymenttermlist != ''){
                global.rootpaymentterm = response.paymenttermlist;
              }

              if(response.rootPaymentTermsNewAgencies != ''){
                const paymenttermlist = response.paymenttermlist.map(term => {
                  const matchingTerm = response.rootPaymentTermsNewAgencies.find(
                    agencyTerm => agencyTerm.term === term.value
                  );
                  
                  return {
                    ...term,
                    status: matchingTerm ?  matchingTerm.status : false
                  }
                })
                
                global.isRootPaymentTermsNewAgencies = true
                global.rootpaymenttermnewagencies = paymenttermlist
              } else {
                global.isRootPaymentTermsNewAgencies = false
              }

              // console.log({'response.agencyDefaultModules': response.agencyDefaultModules})
              if (response.agencyDefaultModules != '') {
                global.agencyDefaultModules = response.agencyDefaultModules
              }


              global.checkModuleRole(response.modules,response.setupcomplete);
              if ((global.menuUserType == 'userdownline' || global.menuUserType == 'user') && !global.systemUser) {
                /** FOR AGENCY THAT NEED TO CONNECT THEIR ACCOUNT BEFORE START ADD CLIENT */
                global.statusaccountconnected = response.accountconnected;
                if (response.accountconnected == 'completed' && response.package_id != '') {
                  global.stripeaccountconnected = true;
                }else if (response.accountconnected == '' && response.paymentgateway != 'stripe' && response.package_id != '') {
                  global.stripeaccountconnected = true;
                }else if (response.accountconnected == '' && response.paymentgateway == 'stripe' && (response.package_id != '' && user.manual_bill == 'T')) {
                  global.stripeaccountconnected = true;
                }else if (response.accountconnected == 'failed' && response.paymentgateway == 'stripe' && (response.package_id != '' && user.manual_bill == 'T')) {
                  global.stripeaccountconnected = true;
                }else{
                  global.stripeaccountconnected = false;
                  if (response.accountconnected == '') {
                    global.statusaccountconnected = 'You need to connect your Stripe account prior to adding any clients.(go to system setting->general setting->connect your account)';
                  }else if (response.accountconnected == 'pending') {
                    global.statusaccountconnected = 'Please continue complete the form to connect your account with Stripe before start to add client (go to system setting->general setting->connect your account)';
                  }else if (response.accountconnected == 'inverification') {
                    global.statusaccountconnected = 'Your account is still being verified by Stripe, please check your email or login to your Stripe account for additional steps required by Stripe.';
                  }

                  if (!window.hasShownAccountConnectNotify && user.customer_card_id != '' && global.statusaccountconnected != 'completed' && user.manual_bill == 'F') {
                    window.hasShownAccountConnectNotify = true;
                    Vue.prototype.$notify({
                        id:'popstatusaccountconnect',
                        message: global.statusaccountconnected,
                        timeout: 0,
                        icon: 'fas fa-megaphone',
                        horizontalAlign: 'right',
                        verticalAlign: 'top',
                        type: 'danger',
                        ignoreDuplicates: true,
                      });
                  }
                  
                }
                /** FOR AGENCY THAT NEED TO CONNECT THEIR ACCOUNT BEFORE START ADD CLIENT */

                /** WARNING FOR AGENCY NOT HAVE CUSTOMER CARD YET */
                if (user.customer_card_id == '' && user.profile_setup_completed == 'T') {
                  if (!document.getElementById('popstatuspaymentsetupfailed')) {
                    Vue.prototype.$notify({
                        id:'popstatuspaymentsetupfailed',
                        message: 'Complete your payment setup first to fully use the system. (Go to Profile -> Payment Setup)',
                        timeout: 0,
                        icon: 'fas fa-megaphone',
                        horizontalAlign: 'right',
                        verticalAlign: 'top',
                        type: 'danger',
                        ignoreDuplicates: true,
                    });
                  }
                }
                /** WARNING FOR AGENCY NOT HAVE CUSTOMER CARD YET */

                /** WARNING FOR AGENCY ALREADY SETUP EVERYTHING JUST NOT COMPLETED THE PROFILE */
                if (user.customer_card_id != '' && user.profile_setup_completed == 'T' && !global.menuLeadsPeek) {
                  if (!window.popstatuscompleteprofile) {
                    window.popstatuscompleteprofile = true;
                    Vue.prototype.$notify({
                        id:'popstatuscompleteprofile',
                        message: 'Please complete your profile to fully use the system. (Go to Profile)',
                        timeout: 0,
                        icon: 'fas fa-megaphone',
                        horizontalAlign: 'right',
                        verticalAlign: 'top',
                        type: 'danger',
                        ignoreDuplicates: true,
                    });
                  }
                }
                /** WARNING FOR AGENCY ALREADY SETUP EVERYTHING JUST NOT COMPLETED THE PROFILE */

              }else if (global.menuUserType == 'sales' && global.systemUser) {
                  global.statusaccountconnected = response.accountconnected;

                  if (response.accountconnected == 'completed') {
                    global.stripeaccountconnected = true;
                  }else{
                    global.stripeaccountconnected = false;
                    if (response.accountconnected == '') {
                      global.statusaccountconnected = 'You need to connect your Stripe account to activated your Sales Account';
                    }else if (response.accountconnected == 'pending') {
                      global.statusaccountconnected = 'Please continue complete the form to connect your account with Stripe before start';
                    }else if (response.accountconnected == 'inverification') {
                      global.statusaccountconnected = 'Your account is still being verified by Stripe, please check your email or login to your Stripe account for additional steps required by Stripe.';
                    }

                    if (!window.hasShownAccountConnectNotify && user.customer_card_id == '' && global.statusaccountconnected != 'completed') {
                      window.hasShownAccountConnectNotify = true;
                      Vue.prototype.$notify({
                          id:'popstatusaccountconnect',
                          message: global.statusaccountconnected,
                          timeout: 0,
                          icon: 'fas fa-megaphone',
                          horizontalAlign: 'right',
                          verticalAlign: 'top',
                          type: 'danger',
                          ignoreDuplicates: true,
                        });
                    }

                  }
              }

              if (typeof to.meta.menuname != 'undefined') {
                if (to.meta.menuname == 'menuAdsDesign') {
                  menuEnabled = Vue.prototype.$global.menuAdsDesign
                }else if (to.meta.menuname == 'menuCampaign') {
                  menuEnabled = Vue.prototype.$global.menuCampaign
                }else if (to.meta.menuname == 'menuLeadsPeek') {
                  menuEnabled = Vue.prototype.$global.menuLeadsPeek
                }else if (to.meta.menuname == 'settingMenuShow') {
                  menuEnabled = Vue.prototype.$global.settingMenuShow
                }
              }
              
              if (user.customer_card_id != '') {
                global.creditcardsetup = true;

                if(global.agency_onboarding_status && response.setupcomplete == 'F'){
                  global.creditcardsetup = false;
                }
              }else{
                global.creditcardsetup = false;
              }

              /* IF AGENCY EMM NOT AGGREE DATA WALLET */
              checkAgencyDataWalletAgreement(to, from, next, store, global, response.isDataWalletAgree);
              /* IF AGENCY EMM NOT AGGREE DATA WALLET */

              if (user.questionnaire_setup_completed == 'T') {
                global.questionnairesetup = true;
              }else{
                global.questionnairesetup = false;
              }

              if(to.name == 'Card Setting' && (user.customer_card_id == '' || response.setupcomplete == 'F')) {
                next({
                  name: 'Profile Setup',
                })
              }else if (menuEnabled && to.meta.clientTypeAccess.includes(store.getters.getUserType)) {
                // next()
                if (global.menuUserType == 'sales') {
                  if (to.path !== '/configuration/sales-connect-account') {
                    return next({ path: '/configuration/sales-connect-account' });
                  } else {
                    return next(); // udah di tujuan
                  }
                } else {
                  if ( (global.userTypeOri == 'sales' && !global.systemUser) || global.menuUserType == 'sales' ) {
                    const oriUsr = localStorage.getItem('userDataOri');
                    localStorage.removeItem('userData');
                    localStorage.removeItem('userDataOri');
                    
                    store.dispatch('updateUserData', oriUsr);
                    localStorage.removeItem('userDataOri');
                    localStorage.removeItem('subdomainAgency');
                    localStorage.removeItem('rootcomp');
                    store.dispatch('setUserData', {
                            user: oriUsr,
                    });
                    window.document.location = "/configuration/sales-connect-account/";
                  } else {
                    next()
                  }
                }
                
              }else{
                if (!to.meta.clientTypeAccess.includes(store.getters.getUserType)) {
                  next({ name: '404notfound' });
                }
                
                if (to.name === 'Integration List' && user.user_type === 'client' && !global.hasVisibleIntegrations) {
                  next({ name: 'Dashboard' });
                  return;
                }
                
                if((global.agency_onboarding_status && response.setupcomplete == 'T') || (response.setupcomplete == 'T' && global.creditcardsetup)){
                  //global.menuLeadsPeek = true;
                  //global.settingMenuShow = true;
                  return next();
                }

                next({
                  name: 'Profile Setup',
                })
              }
          },error => {
              console.log('Token expired');
              localStorage.removeItem('access_token');
              localStorage.removeItem('userData');
              localStorage.removeItem('userRole');
              localStorage.removeItem('userDataOri');
              document.location = "/";
              global.isLoadingContent = false;
          });
      }

      /** VALIDATE MODULE AUTHORIZED */

      /*if(to.meta.menuEnabled && to.meta.clientTypeAccess.includes(store.getters.getUserType)) {
        next()
      }else{
        next({
          name: 'Profile Setup',
        })
      }*/
      //if(to.matched.some(record => record.meta.menuEnabled) && ) {

      //}
      //next()
    }
  } else if (to.matched.some(record => record.meta.requiresVisitor)) {
    if (store.getters.loggedIn) {
      /* IF SSO, AUTO LOGOUT CURRENT USER AND LOGIN NEW USER */
      if(to.path === '/sso' && to.query.token) {
        // validation token
        try {
          const response = await store.dispatch('ssoValidationToken', {
            subdomain: window.location.hostname,
            token: to.query.token,
            currentUserID: store.getters.userData.id
          });

          // console.log(response);

          if(response.status == 200 && response.data.message == "Access Token Valid") {
            const response2 = await store.dispatch('retrieveUserData',{})

            if(response2 == 'success') {
              var global = Vue.prototype.$global
              
              const userData = store.getters.userData;
                        
              /** SET TO STORAGE FOR SIDEMENU */
              // userData.leadlocalname = global.globalModulNameLink.local.name;
              // userData.leadlocalurl = global.globalModulNameLink.local.url;

              // userData.leadlocatorname = global.globalModulNameLink.locator.name;
              // userData.leadlocatorurl = global.globalModulNameLink.locator.url;
              
              global.globalModulNameLink.local.name = userData.leadlocalname;
              global.globalModulNameLink.local.url = userData.leadlocalurl;

              global.globalModulNameLink.locator.name = userData.leadlocatorname;
              global.globalModulNameLink.locator.url = userData.leadlocatorurl;

              global.globalModulNameLink.enhance.name = userData.leadenhancename;
              global.globalModulNameLink.enhance.url = userData.leadenhanceurl;

              global.globalModulNameLink.b2b.name = userData.leadb2bname;
              global.globalModulNameLink.b2b.url = userData.leadb2burl;

              global.globalModulNameLink.simplifi.name = userData.leadsimplifiname;
              global.globalModulNameLink.simplifi.url = userData.leadsimplifiurl;

              userData.idsys = global.idsys;

              const updatedData = {
                leadlocalname: global.globalModulNameLink.local.name,
                leadlocalurl: global.globalModulNameLink.local.url,
                leadlocatorname: global.globalModulNameLink.locator.name,
                leadlocatorurl: global.globalModulNameLink.locator.url,
                leadenhancename: global.globalModulNameLink.enhance.name,
                leadenhanceurl: global.globalModulNameLink.enhance.url,
                leadb2bname: global.globalModulNameLink.b2b.name,
                leadb2burl: global.globalModulNameLink.b2b.url,
                leadsimplifiname: global.globalModulNameLink.simplifi.name,
                leadsimplifiurl: global.globalModulNameLink.simplifi.url,
                idsys: global.idsys
              }

              store.dispatch('updateUserData', updatedData);
              /** SET TO STORAGE FOR SIDEMENU */
              
              const usetupcompleted = store.getters.userData.profile_setup_completed
              if (usetupcompleted == "F") {
                window.document.location = "/user/profile-setup";
              }else{
                //const userData = store.getters.userData;
                if (userData.menuLeadspeek == true && userData.user_type == 'client' && userData.leadspeek_type == 'locator') {
                  window.document.location = "/" + global.globalModulNameLink.locator.url + '/dashboard';
                }else if (userData.menuLeadspeek == true && userData.user_type == 'client' && userData.leadspeek_type == 'local') {
                  window.document.location = "/" + global.globalModulNameLink.local.url + '/dashboard';
                // }else if (((userData.user_type == 'sales' && userData.status_acc != 'completed') || (userData.user_type == 'sales' && userData.status_acc == 'completed' && userData.isAccExecutive == 'F') ) && userData.systemuser) {
                }else if (((userData.user_type == 'sales' && userData.status_acc != 'completed') || (userData.user_type == 'sales' && userData.status_acc == 'completed') ) && userData.systemuser) {
                  window.document.location = "/configuration/sales-connect-account";
                // }else if ((userData.user_type == 'user' || userData.user_type == 'userdownline' || userData.user_type == 'sales') && userData.systemuser) {
                }else if ((userData.user_type == 'user' || userData.user_type == 'userdownline') && userData.systemuser) {
                  window.document.location = "/configuration/agency-list";
                }else if ((userData.user_type == 'user' || userData.user_type == 'userdownline') && (userData.charges_enabled == false || userData.payouts_enabled == false)) {
                  window.document.location = "/configuration/general-setting";
                }else if (userData.user_type == 'user') {
                  window.document.location = "/" + global.globalModulNameLink.local.url + '/dashboard';
                }else{
                  //window.document.location = "/user/profile-setup";
                  window.document.location = "/" + global.globalModulNameLink.local.url + '/dashboard';
                }
              }
            }
            else{
              swal.fire({
                icon: 'error',
                title: 'Sorry!',
                text: 'There is currently an update processing on the platform. Please wait five minutes and try again.',
              })
            }
          }

        } 
        catch (error) {
          console.log(error);
        }
        // validation token
      }
      /* IF SSO, AUTO LOGOUT CURRENT USER AND LOGIN NEW USER */

      var user = store.getters.userData
      var defaultRoute = 'Profile Setup';
      if (user['systemuser']) {
        defaultRoute = 'Agency List';
      }else if (user['profile_setup_completed'] == 'T' && user.user_type == 'userdownline' && user.payouts_enabled && user.charges_enabled){
        defaultRoute = 'Dashboard'
      }else if (user['profile_setup_completed'] == 'T' && user.user_type == 'userdownline' && !user.payouts_enabled && !user.charges_enabled){
        //defaultRoute = 'General Setting'
        window.document.location = "/configuration/general-setting";
      }
      next({
        name: defaultRoute,
      })
    } else {
      next()
    }
  } else {
    next()
  }
})

router.afterEach((to, from) => {
  Vue.prototype.$global.isLoadRouterView = true;
});

/* eslint-disable no-new */
new Vue({
  el: '#app',
  render: h => h(App),
  router,
  store: store, 
  i18n
});