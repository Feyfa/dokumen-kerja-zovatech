import { store } from 'src/store/store'
import Global from 'src/plugins/global';
import Vue from 'vue';
import DashboardLayout from 'src/pages/Layout/DashboardLayout.vue';
import AuthLayout from 'src/pages/Layout/AuthLayout.vue';
import axios from "axios";

// GeneralViews
import NotFound from 'src/pages/GeneralPage/NotFoundPage.vue';

axios.defaults.baseURL = process.env.VUE_APP_DATASERVER_URL + "/api";

//console.log(store.getters.userData.leadlocalurl);
/** PAGES NEED AUTH */
const Dashboard = () =>
  import(/* webpackChunkName: "dashboard" */ 'src/pages/Modules/Auth/Dashboard/index.vue');
const UserProfile = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/Auth/User/UserProfile.vue');
const CardSetting = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/Auth/User/CardSetting.vue');
const Questionnaire = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/Auth/User/Questionnaire.vue');
const QuestionnaireAdd = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/Auth/User/QuestionnaireAdd.vue');
const UserSetup = () => import('src/pages/Modules/Auth/User/UserSetup.vue');  
const UserSetupV1 = () => import('src/pages/Modules/Auth/User/UserSetupV1.vue');  

/** USER AREA */
let UserMenu = {
    path: '/user',
    component: DashboardLayout,
    name: 'User',
    redirect: '/user/profile',
    children: [
      {
        path: 'profile',
        name: 'Profile',
        components: { default: UserProfile },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','client','userdownline','administrator'],
            menuEnabled: true,
        }
      },
      {
        path: 'profile-setup',
        name: 'Profile Setup',
        components: { default: UserSetup },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','client','userdownline','administrator'],
            menuEnabled: true,
        }
      },
      {
        path: 'profile-setup-v1',
        name: 'Profile Setup V1',
        components: { default: UserSetupV1 },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','client','userdownline','administrator'],
            menuEnabled: true,
        },
        beforeEnter: (to, from, next) => {
          if ((global.creditcardsetup === false || !global.menuLeadsPeek) && global.systemUser === false) {
            next({ name: 'Profile Setup' });
          } else {
            next();
          }
        }
      },
      {
        path: 'card-setting',
        name: 'Card Setting',
        components: { default: CardSetting },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','client','userdownline','administrator'],
            menuEnabled: true,
        }
      },
      {
        path: 'questionnaire',
        name: 'Questionnaire Page',
        components: { default: Questionnaire },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator'],
            menuEnabled: true,
        }
      },
      {
        path: 'questionnaire-add',
        name: 'Questionnaire Add Page',
        components: { default: QuestionnaireAdd },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','client','userdownline','administrator'],
            menuEnabled: true,
        }
      },
    ]
};
/** USER AREA */

/** BANNER */
const BannerCreate = () => import('src/pages/Modules/Auth/Banner/Create.vue');  
const BannerList = () => import('src/pages/Modules/Auth/Banner/List.vue'); 

let BannerMenu = {
  path: '/banner',
  component: DashboardLayout,
  name: 'Banner',
  meta: {
    requiresAuth: true,
    clientTypeAccess: ['user','client','userdownline','administrator'],
    menuEnabled: true,
    menuname: 'menuAdsDesign',
  },
  children : [
    {
      path: 'create',
      name: 'Create Banner',
      components: { default: BannerCreate },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','client','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'menuAdsDesign',
        }
    },

    {
      path: 'list',
      name: 'List Banner',
      components: { default: BannerList },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','client','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'menuAdsDesign',
        }
    },

  ]
};
/** BANNER */

// Integration
const IntegrationMain = () => import('src/pages/Integration/Integrations.vue');  
const IntegrationDetails = () => import('src/pages/Integration/IntegrationDetail.vue');  
let Integration = {
  path: '/',
  component: DashboardLayout,
  name: 'Integration',
  meta: {
    requiresAuth: true,
    clientTypeAccess: ['client','userdownline'],
    menuEnabled: true,
  },
  children : [
    {
      path: 'integrations',
      name: 'Integration List',
      components: { default: IntegrationMain },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['client','userdownline'],
            menuEnabled: true,
        }
    },
    {
      // redirect slug lama ke slug baru
      path: 'integrations/gohighlevel',
      redirect: 'integrations/leadconnector'
    }, 
    {
      path: 'integrations/:slug',
      name: 'Integration Detail',
      components: { default: IntegrationDetails },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['client','userdownline'],
            menuEnabled: true,
        }
    },

  ]
};
// integration

/** CAMPAIGN */
const CampaignIndex = () => import('src/pages/Modules/Auth/Campaign/Index.vue');
const CampaignSetup = () => import('src/pages/Modules/Auth/Campaign/CampaignSetup.vue');
const CampaignAudience = () => import('src/pages/Modules/Auth/Campaign/Audience.vue');
const CampaignClient = () => import('src/pages/Modules/Auth/Campaign/Client.vue');

let CampaignMenu = {
  path: '/',
  component: DashboardLayout,
  name: 'Campaign Layout',
  meta: {
      requiresAuth: true,
      clientTypeAccess: ['user','client','userdownline','administrator'],
      menuEnabled: true,
      menuname: 'menuCampaign',
  },
  children: [
    {
      path: 'campaign',
      name: 'Campaign',
      components: { default: CampaignIndex },
      meta: {
        requiresAuth: true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuCampaign',
      }
    },
    {
      path: 'campaign-setup',
      name: 'Build Your Campaign',
      components: { default: CampaignSetup },
      meta: {
          requiresAuth: true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuCampaign',
      }
    },
    {
      path: 'Audience',
      name: 'Audience',
      components: { default: CampaignAudience },
      meta: {
          requiresAuth: true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuCampaign',
      }
    },
    {
      path: 'Client',
      name: 'CampaignClient',
      components: { default: CampaignClient },
      meta: {
          requiresAuth: true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuCampaign',
      }
    },

  ]
};
/** CAMPAIGN */

/** LEADSPEEK */
const LeadspeekDashboard = () => import('src/pages/Modules/Auth/Leedspeek/Dashboard.vue');
const LeadspeekClient = () => import('src/pages/Modules/Auth/Leedspeek/Client.vue');
const LeadspeekClientV1 = () => import('src/pages/Modules/Auth/Leedspeek/V1Client.vue');
const LeadspeekManagement = () => import('src/pages/Modules/Auth/Leedspeek/Leads.vue');

let LeadspeekMenu = {
  path: '/' + encodeURIComponent(store.getters.userData.leadlocalurl),
  component: DashboardLayout,
  name: store.getters.userData.leadlocalname,
  redirect: '/' + store.getters.userData.leadlocalurl + '/dashboard',
  meta: {
    requiresAuth:true,
    clientTypeAccess: ['user','client','userdownline','administrator'],
    menuEnabled: true,
    menuname: 'menuLeadsPeek',
  },
  children: [
    {
      path: 'dashboard',
      name: 'Dashboard',
      components: { default: LeadspeekDashboard },
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuLeadsPeek',
      },
      beforeEnter: (to, from, next) => {
        if (global.systemUser === true) {
          next({ name: 'Agency List' });
        } else {
          next();
        }
      }
    },
    {
      path: 'campaign-management',
      name: 'Campaign Management',
      components: { default:  LeadspeekClientV1},
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuLeadsPeek',
        itemname: 'campaignmanagement',
      },
      beforeEnter: (to, from, next) => {
        if (global.systemUser === true) {
          next({ name: 'Agency List' });
        } else {
          next();
        }
      }
    },
    {
      path: 'campaign-management-v1',
      name: 'Campaign Management V1',
      components: { default: LeadspeekClient},
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuLeadsPeek',
        itemname: 'campaignmanagement',
        isStripeConnected: true,
      }
    },
    {
      path: 'leads-management',
      name: 'Leads Management',
      components: { default: LeadspeekManagement },
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['administrator'],
        menuEnabled: false,
        menuname: 'menuLeadsPeek',
      }
    },

  ]
};
/** LEADSPEEK */

/** LEADSPEEK LOCATOR*/
const LeadspeekDashboardLocator = () => import('src/pages/Modules/Auth/LeedspeekLocator/Dashboard.vue');
const LeadspeekClientLocator = () => import('src/pages/Modules/Auth/LeedspeekLocator/Client.vue');
const LeadspeekManagementLocator = () => import('src/pages/Modules/Auth/LeedspeekLocator/Leads.vue');
const LeadspeekClientLocatorV1 = () => import('src/pages/Modules/Auth/LeedspeekLocator/V1Client.vue');
let LeadspeekMenuLocator = {
  path: '/' + encodeURIComponent(store.getters.userData.leadlocatorurl),
  component: DashboardLayout,
  name: store.getters.userData.leadlocatorname,
  redirect: '/' + store.getters.userData.leadlocatorurl + '/dashboard',
  meta: {
    requiresAuth:true,
    clientTypeAccess: ['user','client','userdownline','administrator'],
    menuEnabled: true,
    menuname: 'menuLeadsPeek',
  },
  children: [
    {
      path: 'dashboard',
      name: 'Dashboard',
      components: { default: LeadspeekDashboardLocator },
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuLeadsPeek',
      },
      beforeEnter: (to, from, next) => {
        if (global.systemUser === true) {
          next({ name: 'Agency List' });
        } else {
          next();
        }
      }
    },
    {
      path: 'campaign-management',
      name: 'Campaign Management',
      components: { default:  LeadspeekClientLocatorV1},
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuLeadsPeek',
        itemname: 'campaignmanagement',
      },
      beforeEnter: (to, from, next) => {
        if (global.systemUser === true) {
          next({ name: 'Agency List' });
        } else {
          next();
        }
      }
    },
    {
      path: 'campaign-management-v1',
      name: 'Campaign Management V1',
      components: { default:  LeadspeekClientLocator},
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuLeadsPeek',
        itemname: 'campaignmanagementV1',
      }
    },
    {
      path: 'leads-management',
      name: 'Leads Management',
      components: { default: LeadspeekManagementLocator },
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['administrator'],
        menuEnabled: false,
        menuname: 'menuLeadsPeek',
      }
    },

  ]
};
/** LEADSPEEK LOCATOR*/

/** ADMINISRATOR AREA */
const ConfigurationClient = () => import('src/pages/Modules/Auth/ConfigApp/Client.vue');
const ConfigurationDownline = () => import('src/pages/Modules/Auth/ConfigApp/Downline.vue');
const ConfigurationAdministrator = () => import('src/pages/Modules/Auth/ConfigApp/Administrator.vue');
const ConfigurationRole = () => import('src/pages/Modules/Auth/ConfigApp/Role.vue');
const ConfigurationSetting= () => import('src/pages/Modules/Auth/ConfigApp/GeneralSetting.vue');
const ConfigurationDataEnrichment = () => import('src/pages/Modules/Auth/ConfigApp/DataEnrichment.vue');
const ConfigurationOptOutList =  () => import('src/pages/Modules/Auth/ConfigApp/OptOutList.vue');
const ConfigurationReportAnalytics = () => import('src/pages/Modules/Auth/ConfigApp/ReportAnalytic.vue');
const ConfigurationSalesAccountList = () => import('src/pages/Modules/Auth/ConfigApp/SalesAccountList.vue');
const ConfigurationSalesConnectAccount = () => import('src/pages/Modules/Auth/ConfigApp/SalesConnectAccount.vue');

let ConfigurationMenu = {
  path: '/configuration',
  component: DashboardLayout,
  name: 'Configuration',
  redirect: '/configuration/client-management',
  meta: {
      requiresAuth: true,
      clientTypeAccess: ['user','userdownline','administrator'],
      menuEnabled: true,
      menuname: 'settingMenuShow',
  },
  children : [
    {
      path: 'client-management',
      name: 'Client List',
      components: { default: ConfigurationClient },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        },
        beforeEnter: (to, from, next) => {
          if (global.systemUser === true) {
            next({ name: 'Agency List' });
          } else {
            next();
          }
        }
    },

    {
      path: 'agency-list',
      name: 'Agency List',
      components: { default: ConfigurationDownline },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator','sales'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        },
        beforeEnter: (to, from, next) => {
          if (global.systemUser === false) {
            next({ name: '404notfound' });
          } else {
            next();
          }
        }
    },

    {
      path: 'administrator-list',
      name: 'Administrator List',
      components: { default: ConfigurationAdministrator },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        }
    },

    {
      path: 'sales-account-list',
      name: 'Sales Rep & AE List',
      components: { default: ConfigurationSalesAccountList },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        },
      beforeEnter: (to, from, next) => {
        if (global.systemUser === false) {
          next({ name: '404notfound' });
        } else {
          next();
        }
      }
    },

    {
      path: 'role-list',
      name: 'Role List',
      components: { default: ConfigurationRole},
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        }
    },

    {
      path: 'general-setting',
      name: 'General Setting',
      components: { default: ConfigurationSetting},
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator', 'sales'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        },
      beforeEnter: (to, from, next) => {
        if (global.menuUserType == 'sales') {
          next({ name: '404notfound' });
        } else {
          next();
        }
      }
    },

    {
      path: 'data-enrichment',
      name: 'Data Enrichment',
      components: { default: ConfigurationDataEnrichment },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        }
    },

    {
      path: 'opt-out-list',
      name: 'Opt-Out List',
      components: { default: ConfigurationOptOutList },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        },
      beforeEnter: (to, from, next) => {
        if (global.systemUser === false) {
          next({ name: '404notfound' });
        } else {
          next();
        }
      }
    },

    {
      path: 'report-analytics',
      name: 'Report Analytics',
      components: { default: ConfigurationReportAnalytics },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['user','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        },
      beforeEnter: (to, from, next) => {
        const deniedAccessReportAnalytics = global.user_permissions && global.user_permissions.report_analytics !== true
        
        if ((global.rootcomp !== true  || global.systemUser !== true || deniedAccessReportAnalytics) && global.globalviewmode === false) {
          next({ name: '404notfound' });
        } else {
          next();
        }
      }
    },
    {
      path: 'sales-connect-account',
      name: 'Sales Connect Account',
      components: { default: ConfigurationSalesConnectAccount },
        meta: {
            requiresAuth: true,
            clientTypeAccess: ['sales'],
            menuEnabled: true,
            menuname: 'settingMenuShow',
        }
    },

  ]
};
/** ADMINISRATOR AREA */

const MarketingServicesAgreementDeveloper = () => import('src/components/Pages/ServicesAgreement/MarketingServicesAgreementDeveloper.vue');

let marketingServicesAgreementDeveloper = {
  path: '/marketing-services-agreement-developer',
  name: 'Marketing Services Agreement Developer',
  components: { default: MarketingServicesAgreementDeveloper },
  meta: {
    requiresVisitor: false,
  }
}

/** PAGES NEED AUTH */

/** PAGES FOR AUTH */
const Login = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/Login.vue');
const Register = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/Register.vue');
const AgencyRegister = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/AgencyRegister.vue');
const ResetPassword = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/ResetPassword.vue');
const InvalidResetPassword = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/InvalidResetPassword.vue');
const PrivacyPolicy = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/PrivacyPolicy.vue');
const TermUse = () =>
import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/TermUse.vue');
const ServiceBillingAgreement = () =>
import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/ServiceBillingAgreement.vue');
const DocsDownload = () =>
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/DocsDownload.vue');
const Sso = () => 
  import(/* webpackChunkName: "pages" */ 'src/pages/Modules/NoAuth/Sso.vue')

let authPages = {
  path: '/',
  component: AuthLayout,
  name: 'Authentication',
  children: [
    {
      path: '/login',
      name: 'Login',
      component: Login,
      meta: {
        requiresVisitor: true,
      }
    },
    {
      path: '/sso',
      name: 'Sso',
      component: Sso,
      meta: {
        requiresVisitor: true,
      }
    },
    {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { requiresVisitor: true },
    beforeEnter: (to, from, next) => {
      const isRegisterAccount = localStorage.getItem('isRegisterAccount') === 'true'
      if (isRegisterAccount) {
        next()
      } else {
        next({ name: 'Login' }) 
      }
    }
  },
    {
      path: '/agency-register/:referralCode?',
      name: 'Agency Register',
      component: AgencyRegister,
      meta: {
        requiresVisitor: true,
      }
    },
    {
      path: '/reset-password-invalid',
      name: 'InvalidResetPassword',
      component: InvalidResetPassword,
      meta: {
        requiresVisitor: true
      }
    },
    {
      path: '/set-new-password/:token',
      name: 'ResetPassword',
      component: ResetPassword,
      meta: {
        requiresVisitor: true,
      },
      beforeEnter: async (to, from, next) => {
        const token = to.params.token;
        const email = to.query.email;

        try {          
          const res = await axios.post('/verify-reset-token', {
            token,
            email,
          });

          if (res.data.valid) {
            next();
          } else {
            next({ name: 'InvalidResetPassword', query: { reason: res.data.reason }});
          }
        } catch (err) {
          next({ name: 'InvalidResetPassword', query: { reason: 'invalid' } });
        }
      },
    },
    {
      path: '/privacy-policy',
      name: 'PrivacyPolicy',
      component: PrivacyPolicy,
      meta: {
        requiresVisitor: false,
      }
    },
    {
      path: '/term-of-use',
      name: 'TermUse',
      component: TermUse,
      meta: {
        requiresVisitor: false,
      }
    },
    {
      path: '/service-billing-agreement',
      name: 'ServiceBillingAgreement',
      component: ServiceBillingAgreement,
      meta: {
        requiresVisitor: false,
      }
    },
    {
      path: '/download-auth/:pkdoc',
      name: 'DownloadAuth',
      component: DocsDownload,
      meta: {
        requiresAuth: true,
        clientTypeAccess: ['user','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'Authentication',
      }
    },
  ]
};

/** PAGES FOR AUTH */

const LeadspeekClean = () => 
  import('src/pages/Modules/Auth/LeedspeekClean')

let LeadspeekMenuClean = {
  path: '/cleanid',
  component: DashboardLayout,
  name: 'cleanid',
  redirect: '/cleanid',
  meta: {
    requiresAuth:true,
    clientTypeAccess: ['user','client','userdownline','administrator'],
    menuEnabled: true,
    menuname: 'menuLeadsPeek',
  },
  children: [
    {
      path: '',
      name: 'cleanid-management',
      components: { default:  LeadspeekClean},
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuLeadsPeek',
      },
      beforeEnter: (to, from, next) => {
        if (global.systemUser === true) {
          next({ name: 'Agency List' });
        } else {
          next();
        }
      }
    },
  ]
}

Vue.use(Global);
var global = Vue.prototype.$global
/** DEFINE ROUTES */
let defineRoutes = {
  UserMenu,
  BannerMenu,
  CampaignMenu,
  ConfigurationMenu,
  authPages,
  LeadspeekMenu,
  LeadspeekMenuLocator,
  Integration,
  marketingServicesAgreementDeveloper,
  LeadspeekMenuClean,
};
/** DEFINE ROUTES */


/** LEADSPEEK ENHANCE*/
if(store.getters.userData.leadenhanceurl != null && store.getters.userData.leadenhancename != null) {
  const LeadspeekDashboardEnhance = () => import('src/pages/Modules/Auth/LeedspeekEnhance/Dashboard.vue');
  const LeadspeekClientEnhance = () => import('src/pages/Modules/Auth/LeedspeekEnhance/Client.vue');
  const LeadspeekClientEnhanceV1 = () => import('src/pages/Modules/Auth/LeedspeekEnhance/ClientV1.vue');
  const LeadspeekManagementEnhance = () => import('src/pages/Modules/Auth/LeedspeekEnhance/Leads.vue');

  let LeadspeekMenuEnhance = {
    path: '/' + encodeURIComponent(store.getters.userData.leadenhanceurl),
    component: DashboardLayout,
    name: store.getters.userData.leadenhancename,
    redirect: '/' + store.getters.userData.leadenhanceurl + '/dashboard',
    meta: {
      requiresAuth:true,
      clientTypeAccess: ['user','client','userdownline','administrator'],
      menuEnabled: true,
      menuname: 'menuLeadsPeek',
    },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        components: { default: LeadspeekDashboardEnhance },
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuLeadsPeek',
        },
        beforeEnter: (to, from, next) => {
          if (global.systemUser === true) {
            next({ name: 'Agency List' });
          } else {
            next();
          }
        }
      },
      {
        path: 'campaign-management',
        name: 'Campaign Management',
        components: { default: LeadspeekClientEnhanceV1  },
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuLeadsPeek',
          itemname: 'campaignmanagement',
        },
        beforeEnter: (to, from, next) => {
          if (global.systemUser === true) {
            next({ name: 'Agency List' });
          } else {
            next();
          }
        }
      },
      {
        path: 'campaign-management-v1',
        name: 'Campaign Management v1',
        components: { default:  LeadspeekClientEnhance},
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuLeadsPeek',
          itemname: 'campaignmanagementV1',
        }
      },
      {
        path: 'leads-management',
        name: 'Leads Management',
        components: { default: LeadspeekManagementEnhance },
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['administrator'],
          menuEnabled: false,
          menuname: 'menuLeadsPeek',
        }
      },
  
    ]
  };

  defineRoutes = {LeadspeekMenuEnhance, ...defineRoutes};
}
/** LEADSPEEK ENHANCE*/

/** LEADSPEEK B2B*/
if(store.getters.userData.leadb2burl != null && store.getters.userData.leadb2bname != null) {
  const LeadspeekDashboardB2b = () => import('src/pages/Modules/Auth/LeedspeekB2b/Dashboard.vue');
  const LeadspeekClientB2b = () => import('src/pages/Modules/Auth/LeedspeekB2b/Client.vue');
  const LeadspeekClientB2bV1 = () => import('src/pages/Modules/Auth/LeedspeekB2b/ClientV1.vue');
  const LeadspeekManagementB2b = () => import('src/pages/Modules/Auth/LeedspeekB2b/Leads.vue');

  let LeadspeekMenuB2b = {
    path: '/' + encodeURIComponent(store.getters.userData.leadb2burl),
    component: DashboardLayout,
    name: store.getters.userData.leadb2bname,
    redirect: '/' + store.getters.userData.leadb2burl + '/dashboard',
    meta: {
      requiresAuth:true,
      clientTypeAccess: ['user','client','userdownline','administrator'],
      menuEnabled: true,
      menuname: 'menuLeadsPeek',
    },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        components: { default: LeadspeekDashboardB2b },
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuLeadsPeek',
        },
        beforeEnter: (to, from, next) => {
          if (global.systemUser === true) {
            next({ name: 'Agency List' });
          } else {
            next();
          }
        }
      },
      {
        path: 'campaign-management',
        name: 'Campaign Management',
        components: { default: LeadspeekClientB2bV1  },
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuLeadsPeek',
          itemname: 'campaignmanagement',
        },
        beforeEnter: (to, from, next) => {
          if (global.systemUser === true) {
            next({ name: 'Agency List' });
          } else {
            next();
          }
        }
      },
      {
        path: 'campaign-management-v1',
        name: 'Campaign Management v1',
        components: { default:  LeadspeekClientB2b},
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuLeadsPeek',
          itemname: 'campaignmanagementV1',
        }
      },
      {
        path: 'leads-management',
        name: 'Leads Management',
        components: { default: LeadspeekManagementB2b },
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['administrator'],
          menuEnabled: false,
          menuname: 'menuLeadsPeek',
        }
      },
  
    ]
  };

  defineRoutes = {LeadspeekMenuB2b, ...defineRoutes};
}
/** LEADSPEEK B2B*/



/** LEADSPEEK SIMPLIFI*/
if(store.getters.userData.leadsimplifiurl != null && store.getters.userData.leadsimplifiname != null) {
  const LeadspeekClientSimplifi = () => import('src/pages/Modules/Auth/LeedspeekSimplifi/Client.vue');
  let LeadspeekMenuSimplifi = {
    path: '/' + encodeURIComponent(store.getters.userData.leadsimplifiurl),
    component: DashboardLayout,
    name: store.getters.userData.leadsimplifiname,
    redirect: '/' + store.getters.userData.leadb2burl + '/dashboard',
    meta: {
      requiresAuth:true,
      clientTypeAccess: ['user','client','userdownline','administrator'],
      menuEnabled: true,
      menuname: 'menuLeadsPeek',
    },
    children: [
      {
        path: 'campaign-management',
        name: 'Campaign Management',
        components: { default: LeadspeekClientSimplifi  },
        meta: {
          requiresAuth:true,
          clientTypeAccess: ['user','client','userdownline','administrator'],
          menuEnabled: true,
          menuname: 'menuLeadsPeek',
          itemname: 'campaignmanagement',
        },
        beforeEnter: (to, from, next) => {
          if (global.systemUser === true) {
            next({ name: 'Agency List' });
          } else {
            next();
          }
        }
      },
  
    ]
  };

  defineRoutes = {LeadspeekMenuSimplifi, ...defineRoutes};
}
/** LEADSPEEK SIMPLIFI*/

/* LEADSPEEK PREDICT */
if(store.getters.userData.leadpredicturl != null && store.getters.userData.leadpredictname != null) {
  const LeadspeekClientPredict = () => import('src/pages/Modules/Auth/LeedspeekPredict/Client.vue');
  
  let LeadspeekMenuPredict = {
      path: '/' + encodeURIComponent(store.getters.userData.leadpredicturl),
      component: DashboardLayout,
      name: store.getters.userData.leadpredictname,
      redirect: '/' + store.getters.userData.leadb2burl + '/dashboard',
      meta: {
        requiresAuth:true,
        clientTypeAccess: ['user','client','userdownline','administrator'],
        menuEnabled: true,
        menuname: 'menuLeadsPeek',
      },
      children: [
        {
          path: 'campaign-management',
          name: 'Campaign Management',
          components: { default: LeadspeekClientPredict  },
          meta: {
            requiresAuth:true,
            clientTypeAccess: ['user','client','userdownline','administrator'],
            menuEnabled: true,
            menuname: 'menuLeadsPeek',
            itemname: 'campaignmanagement',
          },
          beforeEnter: (to, from, next) => {
            if (global.systemUser === true) {
              next({ name: 'Agency List' });
            } else {
              next();
            }
          }
        },
      ]
  }

  defineRoutes = {LeadspeekMenuPredict, ...defineRoutes};
}
/* LEADSPEEK PREDICT */

/** FINAL ROUTES */
const routes = [
  {
    path: '/',
    redirect: '/login',
    name: 'LoginRoot',
    meta: {
      requiresVisitor: true,
    }
  },
  
  ...Object.values(defineRoutes),
  
  { path: '*', component: NotFound, name: '404notfound' }
];

export default routes;
/** FINAL ROUTES */