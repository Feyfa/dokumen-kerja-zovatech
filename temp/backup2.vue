<template>
    <div
      v-loading.fullscreen.lock="this.$global.isLoadingContent"
      element-loading-spinner="fas fa-spinner fa-spin"
      class="wrapper" 
      :class="{ 'nav-open': $sidebar.showSidebar }"
      >
      <!-- <div id="ofBar" v-if="this.$global.globalviewmode" @click="revertViewMode">
        <div class="ofBar-txt"><i class="far fa-eye"></i></div>
      </div> -->
      <notifications></notifications>
  
      <side-bar
        :background-color="sidebarBackground"
        :short-title="$t('sidebar.shortTitle')"
        :title="$t('sidebar.title')"
      >
      <sidebar-toggle-button />
        <template  slot="links">
          <sidebar-item
            :link="{
              name: $t('sidebar.dashboard'),
              icon: 'fas fa-chart-line',
              path: '/dashboard'
            }"
            v-if="this.$global.menuDashboard"
          >
          </sidebar-item>
  
          <sidebar-item
            :link="{
              name: 'Ads Design',
              icon: 'fas fa-drafting-compass'
            }"
            v-if="this.$global.menuAdsDesign"
          >
              <sidebar-item :link="{ name: 'Banner' }">
                  <sidebar-item
                    :link="{
                      name: 'create',
                      path: '/banner/create',
                    }"
                  ></sidebar-item>
                  <sidebar-item
                    :link="{
                      name: 'list',
                      path: '/banner/list',
                    }"
                  ></sidebar-item>
              </sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Clients',
                      path: '/client'
                    }"
                  ></sidebar-item>
  
          </sidebar-item>
          <sidebar-item
            :link="{
              name: 'Campaign', 
              icon: 'fas fa-bullhorn',
            }"
            v-if="this.$global.menuCampaign"
          >
            <sidebar-item
                    :link="{
                      name: 'Dashboard',
                      path: '/campaign',
                    }"
                  ></sidebar-item>
                  <sidebar-item
                    :link="{
                      name: 'Create Campaign',
                      path: '/campaign-setup'
                    }"
                  ></sidebar-item>
                  <sidebar-item
                    :link="{
                      name: 'Campaign Audience List',
                      path: '/audience'
                    }"
                  ></sidebar-item>
                  <sidebar-item
                    :link="{
                      name: 'Clients',
                      path: '/client'
                    }"
                  ></sidebar-item>
          </sidebar-item>
          <!-- dashboard -->
          <sidebar-item
            :link="{
              name: 'Dashboard',
              icon: 'fa-solid fa-gauge',
              path: '/' + encodeURIComponent(urlDashboard()) + '/dashboard'
            }"
            v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected && (this.activesidebar['local'] || this.activesidebar['locator'] || this.activesidebar['enhance'] || this.activesidebar['b2b'])) || (this.$global.menuLeadsPeek && this.$global.menuUserType == 'client' && this.$global.stripeaccountconnected && !this.$global.systemUser && (this.activesidebar['local'] || this.activesidebar['locator'] || this.activesidebar['enhance'] || this.activesidebar['b2b']))"
            class="--sidebar-custom-item"
            :class="{'--active': $route.path.includes('dashboard')}"
          >
          </sidebar-item>
          <!-- dashboard -->
          <!-- SITE ID -->
          <sidebar-item
            class="--sidebar-custom-item"
            :link="{
              name: this.$global.globalModulNameLink.local.name,
              icon: 'far fa-eye',
              path: '/' + encodeURIComponent(this.$global.globalModulNameLink.local.url) + '/campaign-management'
  
            }"
            v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected && this.activesidebar['local']) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_type == 'local' && this.$global.menuUserType == 'client' && this.$global.stripeaccountconnected && !this.$global.systemUser && this.activesidebar['local'])"
          >
              <!-- <sidebar-item
                    :link="{
                      name: 'Dashboard',
                      path: '/' + this.$global.globalModulNameLink.local.url + '/dashboard'
                    }"
                  ></sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Campaign Management',
                      path: '/' + this.$global.globalModulNameLink.local.url + '/campaign-management'
                    }"
                    v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_type == 'local' && this.$global.menuUserType == 'client' && this.$global.disabledaddcampaign)"
                  ></sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Leads Management',
                      path: '/' + this.$global.globalModulNameLink.local.url + '/leads-management'
                    }"
                    v-if="false"
                  ></sidebar-item> -->
  
          </sidebar-item>
          <!-- SITE ID -->
  
          <!-- SEARCH ID -->
          <sidebar-item
            class="--sidebar-custom-item"
            :link="{
              name: this.$global.globalModulNameLink.locator.name,
              icon: 'fas fa-map-marked',
              path: '/' + encodeURIComponent(this.$global.globalModulNameLink.locator.url) + '/campaign-management'
            }"
            v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected && this.activesidebar['locator']) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_typeLocator == 'locator' && this.$global.menuUserType == 'client' && this.$global.stripeaccountconnected && !this.$global.systemUser && this.activesidebar['locator'])"
          >
              <!-- <sidebar-item
                    :link="{
                      name: 'Dashboard',
                      path: '/' + this.$global.globalModulNameLink.locator.url + '/dashboard'
                    }"
                  ></sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Campaign Management',
                      path: '/' + this.$global.globalModulNameLink.locator.url + '/campaign-management'
                    }"
                    v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType != 'client'  && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_typeLocator == 'locator' && this.$global.menuUserType == 'client' && this.$global.disabledaddcampaign)"
                  ></sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Leads Management',
                      path: '/' + this.$global.globalModulNameLink.locator.url + '/leads-management'
                    }"
                    v-if="false"
                  ></sidebar-item> -->
  
          </sidebar-item>
          <!-- SEARCH ID -->
  
          <!-- ENHANCE SEARCH -->
          <sidebar-item
             class="--sidebar-custom-item"
            :link="{
              name: this.$global.globalModulNameLink.enhance.name,
              icon: 'fa-solid fa-angles-up',
              path: '/' + encodeURIComponent(this.$global.globalModulNameLink.enhance.url) + '/campaign-management'
            }"
            v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected && this.activesidebar['enhance']) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_typeLocator == 'locator' && this.$global.menuUserType == 'client' && this.$global.stripeaccountconnected && !this.$global.systemUser && this.activesidebar['enhance'])) && (this.$global.globalModulNameLink.enhance.name != null) && (this.$global.globalModulNameLink.enhance.url != null)"
          >
              <!-- <sidebar-item
                    :link="{
                      name: 'Dashboard',
                      path: '/' + this.$global.globalModulNameLink.enhance.url + '/dashboard'
                    }"
                  ></sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Campaign Management',
                      path: '/' + this.$global.globalModulNameLink.enhance.url + '/campaign-management'
                    }"
                    v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType != 'client'  && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_typeLocator == 'locator' && this.$global.menuUserType == 'client' && this.$global.disabledaddcampaign)"
                  ></sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Leads Management',
                      path: '/' + this.$global.globalModulNameLink.enhance.url + '/leads-management'
                    }"
                    v-if="false"
                  ></sidebar-item> -->
  
          </sidebar-item>
          <!-- ENHANCE SEARCH -->
  
          <!-- B2B SEARCH -->
          <sidebar-item
             class="--sidebar-custom-item"
            :showBeta="this.$global.betaFeature.b2b_module.is_beta && (this.$global.betaFeature.b2b_module.apply_to_all_agency || this.$global.isBeta)"
            :link="{
              name: this.$global.globalModulNameLink.b2b.name,
              icon: 'fa-solid fa-building',
              path: '/' + encodeURIComponent(this.$global.globalModulNameLink.b2b.url) + '/campaign-management'
            }"
            v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected && this.activesidebar['b2b']) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_typeLocator == 'locator' && this.$global.menuUserType == 'client' && this.$global.stripeaccountconnected && !this.$global.systemUser && this.activesidebar['b2b'])) && (this.$global.globalModulNameLink.b2b.name != null) && (this.$global.globalModulNameLink.b2b.url != null) && (this.validateBetaFeature('b2b_module'))"
          >
              <!-- <sidebar-item
                    :link="{
                      name: 'Dashboard',
                      path: '/' + this.$global.globalModulNameLink.enhance.url + '/dashboard'
                    }"
                  ></sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Campaign Management',
                      path: '/' + this.$global.globalModulNameLink.enhance.url + '/campaign-management'
                    }"
                    v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType != 'client'  && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_typeLocator == 'locator' && this.$global.menuUserType == 'client' && this.$global.disabledaddcampaign)"
                  ></sidebar-item>
  
              <sidebar-item
                    :link="{
                      name: 'Leads Management',
                      path: '/' + this.$global.globalModulNameLink.enhance.url + '/leads-management'
                    }"
                    v-if="false"
                  ></sidebar-item> -->
  
          </sidebar-item>
          <!-- B2B SEARCH -->
  
          <!-- simplifi -->
          <sidebar-item
              class="--sidebar-custom-item"
              :showBeta="this.$global.betaFeature.simplifi_module.is_beta && (this.$global.betaFeature.simplifi_module.apply_to_all_agency || this.$global.isBeta)"
              :link="{
                name: this.$global.globalModulNameLink.simplifi.name,
                icon: 'fa-solid fa-rectangle-ad',
                path: '/' + encodeURIComponent(this.$global.globalModulNameLink.simplifi.url) + '/campaign-management'
              }"
              v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected && this.activesidebar['simplifi']) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_typeLocator == 'locator' && this.$global.menuUserType == 'client' && this.$global.stripeaccountconnected && !this.$global.systemUser && this.activesidebar['simplifi'])) && (this.$global.idsys == this.$global.masteridsys) && (this.validateBetaFeature('simplifi_module'))">
          </sidebar-item>
          <!-- simplifi -->
  
          <!-- CLEAN ID -->
          <sidebar-item
            class="--sidebar-custom-item"
            :showBeta="this.$global.betaFeature.clean_module.is_beta && (this.$global.betaFeature.clean_module.apply_to_all_agency || this.$global.isBeta)"
            :link="{
              name: 'Clean ID',
              icon: 'fa-solid fa-file-import',
              path: '/cleanid'
            }"
            v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && this.$global.creditcardsetup && this.$global.stripeaccountconnected && this.$global.idsys == this.$global.masteridsys && this.$global.apiMode && this.$global.is_marketing_services_agreement_developer && this.validateBetaFeature('clean_module'))">
            <!-- v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && !this.$global.agencyPaymentFailed && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected) || (this.$global.menuLeadsPeek && this.$global.menuLeadsPeek_typeLocator == 'locator' && this.$global.menuUserType == 'client'  && !this.$global.systemUser))"> -->
          </sidebar-item>
          <!-- CLEAN ID -->
           
          <hr v-if="false"/>
          <sidebar-item
            :link="{
              name: 'Add New Campaign',
              icon: 'far fa-plus-circle',
              path: '/user/questionnaire-add'
            }"
            v-if="(this.$global.menuLeadsPeek && this.$global.menuUserType == 'client' && false)"
          >
          </sidebar-item>
  
          <!-- SYSTEM SETTING -->
           
            <sidebar-item
                    v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales') || (this.$global.menuUserType == 'sales' && this.$global.stripeaccountconnected && this.$global.isAccExecutive == 'T')) && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && !this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected"
                    :link="{
                      name: 'Client Management',
                      icon: 'fa-solid fa-user',
                      path: '/configuration/client-management'
                    }"
                     class="--sidebar-custom-item"
              ></sidebar-item>
  
              <!-- v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales') || (this.$global.menuUserType == 'sales' && this.$global.stripeaccountconnected && this.$global.isAccExecutive == 'T')) && this.$global.systemUser" -->
              <sidebar-item
                    v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales')) && this.$global.systemUser"
                    :link="{
                      name: 'Agency List',
                      icon:'fas fa-sitemap',
                      path: '/configuration/agency-list'
                    }"
                     class="--sidebar-custom-item"
              ></sidebar-item>
  
              <sidebar-item
                    v-if="(((this.$global.menuLeadsPeek || (this.$global.creditcardsetup)) && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales') || (this.$global.menuUserType == 'sales' && this.$global.stripeaccountconnected && this.$global.isAccExecutive == 'T')) && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && this.$global.menuUserType != 'sales'"
                    :link="{
                      name: 'Administrator List',
                      icon: 'fa-solid fa-user-tie',
                      path: '/configuration/administrator-list'
                    }"
                     class="--sidebar-custom-item"
              ></sidebar-item>
              <sidebar-item
                  v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales') || (this.$global.menuUserType == 'sales' && this.$global.stripeaccountconnected && this.$global.isAccExecutive == 'T')) && this.$global.systemUser && this.$global.menuUserType != 'sales'"
                  :link="{
                    name: 'Sales Account List',
                    icon: 'fa-solid fa-users',
                    path: '/configuration/sales-account-list'
                  }"
                  class="--sidebar-custom-item"
              ></sidebar-item>
  
  
          
             
              <sidebar-item
                    v-if="(((this.$global.menuLeadsPeek || (this.$global.creditcardsetup)) && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales') || (this.$global.menuUserType != 'sales' && this.$global.stripeaccountconnected && this.$global.isAccExecutive == 'T')) && (!this.$global.agencyPaymentFailed || this.$global.globalviewmode) && this.$global.menuUserType != 'client'"
                    :link="{
                      name: 'General Settings',
                      icon: 'fa-solid fa-gear',
                      path: '/configuration/general-setting'
                    }"
                  class="--sidebar-custom-item"
  
              ></sidebar-item>
              <sidebar-item
                  v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales') || (this.$global.menuUserType == 'sales' && this.$global.stripeaccountconnected && this.$global.isAccExecutive == 'T')) &&  this.$global.systemUser && this.$global.menuUserType != 'sales'"
                  :link="{
                    name: 'Exclusion List',
                    icon: 'fa-solid fa-hand',
                    path: '/configuration/opt-out-list'
                  }"
                    class="--sidebar-custom-item"
              ></sidebar-item>
  
              <sidebar-item
                  v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales' && (this.$global.user_permissions == null || this.$global.user_permissions.report_analytics)) || (this.$global.menuUserType == 'sales' && this.$global.stripeaccountconnected && this.$global.isAccExecutive == 'T')) && this.$global.systemUser && this.$global.menuUserType != 'sales' && this.$global.rootcomp && (this.$global.user_permissions == null || this.$global.user_permissions.report_analytics)"
                  :link="{
                    name: 'Report Analytics',
                    icon: 'fa-solid fa-chart-line',
                    path: '/configuration/report-analytics'
                  }"
                  class="--sidebar-custom-item"
              ></sidebar-item>
           
          <!-- <sidebar-item
            :link="{
              name: 'SYSTEM SETTINGS',
              icon: 'fas fa-tools'
            }"
            v-if="((this.$global.menuLeadsPeek && this.$global.menuUserType != 'client' && this.$global.menuUserType != 'sales') || (this.$global.menuUserType == 'sales' && this.$global.stripeaccountconnected && this.$global.isAccExecutive == 'T'))"
          >
              <sidebar-item
                    v-if="!this.$global.systemUser && this.$global.creditcardsetup && this.$global.stripeaccountconnected"
                    :link="{
                      name: 'Client Management',
                      path: '/configuration/client-management'
                    }"
              ></sidebar-item>
  
              <sidebar-item
                    v-if="this.$global.systemUser"
                    :link="{
                      name: 'Agency List',
                      path: '/configuration/agency-list'
                    }"
              ></sidebar-item>
  
              <sidebar-item
                    v-if="this.$global.menuUserType != 'sales'"
                    :link="{
                      name: 'Administrator List',
                      path: '/configuration/administrator-list'
                    }"
              ></sidebar-item>
  
              <sidebar-item
                  v-if="this.$global.systemUser && this.$global.menuUserType != 'sales'"
                  :link="{
                    name: 'Sales Account List',
                    path: '/configuration/sales-account-list'
                  }"
              ></sidebar-item>
  
              <sidebar-item
                    v-if="this.$global.menuUserType != 'sales' && false"
                    :link="{
                      name: 'Role List',
                      path: '/configuration/role-list'
                    }"
              ></sidebar-item>
  
              <sidebar-item
                    v-if="this.$global.menuUserType != 'client'"
                    :link="{
                      name: 'General Settings',
                      path: '/configuration/general-setting'
                    }"
              ></sidebar-item>
  
              <sidebar-item
                    v-if="this.$global.systemUser && this.$global.menuUserType != 'sales' && false"
                    :link="{
                      name: 'Data Enrichment',
                      path: '/configuration/data-enrichment'
                    }"
              ></sidebar-item>
  
              <sidebar-item
                  v-if="this.$global.systemUser && this.$global.menuUserType != 'sales'"
                  :link="{
                    name: 'Opt-Out List',
                    path: '/configuration/opt-out-list'
                  }"
              ></sidebar-item>
  
              <sidebar-item
                  v-if="this.$global.systemUser && this.$global.menuUserType != 'sales' && this.$global.rootcomp"
                  :link="{
                    name: 'Report Analytics',
                    path: '/configuration/report-analytics'
                  }"
              ></sidebar-item>
  
          </sidebar-item> -->
          <!-- SYSTEM SETTING -->
  
          <div style="height:50px">&nbsp;</div>
          <!--
          <sidebar-item
            :link="{
              name: 'Configuration', 
              icon: 'fas fa-cogs',
            }"
          >
                  <sidebar-item
                    :link="{
                      name: 'Client List',
                      path: '/configuration/client-list',
                    }"
                  ></sidebar-item>
                  <sidebar-item
                    :link="{
                      name: 'Downline List',
                      path: '/configuration/downline-list'
                    }"
                  ></sidebar-item>
                  <sidebar-item
                    :link="{
                      name: 'Administrator List',
                      path: '/configuration/administrator-list'
                    }"
                  ></sidebar-item>
  
                  <sidebar-item
                    :link="{
                      name: 'Role List',
                      path: '/configuration/role-list'
                    }"
                  ></sidebar-item>
                  
          </sidebar-item>
  
          <sidebar-item
            :link="{
              name: 'Report',
              icon: 'fas fa-file-invoice-dollar',
              path: '/report'
            }"
          >
          </sidebar-item>
          -->
          <!--<sidebar-item
              :link="{ name: $t('sidebar.pricing'), path: '/pricing' }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.rtl'), path: '/pages/rtl' }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.timeline'), path: '/pages/timeline' }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.login'), path: '/login' }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.register'), path: '/register' }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.lock'), path: '/lock' }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.userProfile'), path: '/pages/user' }"
            ></sidebar-item>
          </sidebar-item>
          <sidebar-item
            :link="{
              name: $t('sidebar.components'),
              icon: 'tim-icons icon-molecule-40'
            }"
          >
            <sidebar-item :link="{ name: $t('sidebar.multiLevelCollapse') }">
              <sidebar-item
                :link="{
                  name: $t('sidebar.example'),
                  isRoute: false,
                  path: 'https://google.com',
                  target: '_blank'
                }"
              ></sidebar-item>
            </sidebar-item>
  
            <sidebar-item
              :link="{ name: $t('sidebar.buttons'), path: '/components/buttons' }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.gridSystem'),
                path: '/components/grid-system'
              }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.panels'), path: '/components/panels' }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.sweetAlert'),
                path: '/components/sweet-alert'
              }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.notifications'),
                path: '/components/notifications'
              }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.icons'), path: '/components/icons' }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.typography'),
                path: '/components/typography'
              }"
            ></sidebar-item>
          </sidebar-item>
          <sidebar-item
            :link="{ name: $t('sidebar.forms'), icon: 'tim-icons icon-notes' }"
          >
            <sidebar-item
              :link="{ name: $t('sidebar.regularForms'), path: '/forms/regular' }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.extendedForms'),
                path: '/forms/extended'
              }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.validationForms'),
                path: '/forms/validation'
              }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.wizard'), path: '/forms/wizard' }"
            ></sidebar-item>
          </sidebar-item>
          <sidebar-item
            :link="{
              name: $t('sidebar.tables'),
              icon: 'tim-icons icon-puzzle-10'
            }"
          >
            <sidebar-item
              :link="{
                name: $t('sidebar.regularTables'),
                path: '/table-list/regular'
              }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.extendedTables'),
                path: '/table-list/extended'
              }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.paginatedTables'),
                path: '/table-list/paginated'
              }"
            ></sidebar-item>
          </sidebar-item>
          <sidebar-item
            :link="{ name: $t('sidebar.maps'), icon: 'tim-icons icon-pin' }"
          >
            <sidebar-item
              :link="{ name: $t('sidebar.googleMaps'), path: '/maps/google' }"
            ></sidebar-item>
            <sidebar-item
              :link="{
                name: $t('sidebar.fullScreenMaps'),
                path: '/maps/full-screen'
              }"
            ></sidebar-item>
            <sidebar-item
              :link="{ name: $t('sidebar.vectorMaps'), path: '/maps/vector-map' }"
            ></sidebar-item>
          </sidebar-item>
          <sidebar-item
            :link="{
              name: $t('sidebar.widgets'),
              icon: 'tim-icons icon-settings',
              path: '/widgets'
            }"
          ></sidebar-item>
          <sidebar-item
            :link="{
              name: $t('sidebar.charts'),
              icon: 'tim-icons icon-chart-bar-32',
              path: '/charts'
            }"
          ></sidebar-item>
          <sidebar-item
            :link="{
              name: $t('sidebar.calendar'),
              icon: 'tim-icons icon-time-alarm',
              path: '/calendar'
            }"
          ></sidebar-item>
          -->
        </template>
      </side-bar>
      <!-- <sidebar-toggle-button /> -->
      <!--Share plugin (for demo purposes). You can remove it if don't plan on using it-->
      <!-- <sidebar-share :background-color.sync="sidebarBackground"> </sidebar-share> -->
      <div class="main-panel" :data="sidebarBackground">
        <dashboard-navbar></dashboard-navbar>
        <router-view name="header"></router-view>
  
        <div
          :class="{ content: !$route.meta.hideContent }"
          @click="toggleSidebar"
        >
          <div class="breadcrumb-container">
            <route-breadcrumb v-if="$route && $route.matched && $route.matched.length"></route-breadcrumb>
          </div>
          <zoom-center-transition :duration="200" mode="out-in">
            <!-- your content here -->
            <router-view></router-view>
          </zoom-center-transition>
        </div>
        <content-footer v-if="!$route.meta.hideFooter"></content-footer>
      </div>
    </div>
  </template>
  <script>
  /* eslint-disable no-new */
  import { BetaBadge } from '@/components';
  import PerfectScrollbar from 'perfect-scrollbar';
  import 'perfect-scrollbar/css/perfect-scrollbar.css';
  import SidebarShare from './SidebarSharePlugin';
  function hasElement(className) {
    return document.getElementsByClassName(className).length > 0;
  }
  
  function initScrollbar(className) {
    if (hasElement(className)) {
      new PerfectScrollbar(`.${className}`);
    } else {
      // try to init it later in case this component is loaded async
      setTimeout(() => {
        initScrollbar(className);
      }, 100);
    }
  }
  
  import DashboardNavbar from './DashboardNavbar.vue';
  import ContentFooter from './ContentFooter.vue';
  //import DashboardContent from './Content.vue';
  import SidebarToggleButton from './SidebarToggleButton.vue';
  import { SlideYDownTransition, ZoomCenterTransition } from 'vue2-transitions';
  import RouteBreadcrumb from '@/components/Breadcrumb/RouteBreadcrumb.vue';
  
  export default {
    components: {
      DashboardNavbar,
      ContentFooter,
      SidebarToggleButton,
      //DashboardContent,
      //SlideYDownTransition,
      ZoomCenterTransition,
      SidebarShare,
      BetaBadge,
      RouteBreadcrumb,
    },
    data() {
      return {
        sidebarBackground: 'primary', //vue|blue|orange|green|red|primary
        userType: 'user',
        clientsidebar : [],
        activesidebar : {}
      };
    },
    methods: {
      validateBetaFeature(type) {
        if (type == 'b2b_module') {
          if (!this.$global.betaFeature.b2b_module.is_beta || this.$global.betaFeature.b2b_module.apply_to_all_agency || this.$global.isBeta) {
            return true;
          } else {
            return false;
          }
        } else if (type == 'simplifi_module') {
          if (!this.$global.betaFeature.simplifi_module.is_beta || this.$global.betaFeature.simplifi_module.apply_to_all_agency || this.$global.isBeta) {
            return true;
          } else {
            return false;
          }
        } else if (type == 'clean_module') {
          if (!this.$global.betaFeature.clean_module.is_beta || this.$global.betaFeature.clean_module.apply_to_all_agency || this.$global.isBeta) {
            return true;
          } else {
            return false;
          }
        }
        return true;
      },
      revertViewMode() {
        const oriUsr = this.$global.getlocalStorage('userDataOri');
        //this.$global.SetlocalStorage('userData',oriUsr);
        localStorage.removeItem('userData');
        localStorage.removeItem('userDataOri');
        
        // localStorage.setItem('userData',JSON.stringify(oriUsr));
        this.$store.dispatch('updateUserData', oriUsr);
        localStorage.removeItem('userDataOri');
        this.$store.dispatch('setUserData', {
                user: oriUsr,
        });
        window.document.location = "/configuration/agency-list/";
      },
      toggleSidebar() {
        if (this.$sidebar.showSidebar) {
          this.$sidebar.displaySidebar(false);
        }
      },
      initScrollbar() {
        let docClasses = document.body.classList;
        let isWindows = navigator.platform.startsWith('Win');
        //if (isWindows) {
        if (false) {
          // if we are on windows OS we activate the perfectScrollbar function
          initScrollbar('sidebar');
          initScrollbar('main-panel');
          initScrollbar('sidebar-wrapper');
  
          docClasses.add('perfect-scrollbar-on');
        } else {
          docClasses.add('perfect-scrollbar-off');
        }
      },
      urlDashboard(){
        if(this.activesidebar['local']){
          return this.$global.globalModulNameLink.local.url
        } else if (this.activesidebar['locator']){
          return this.$global.globalModulNameLink.locator.url
        } else if (this.activesidebar['enhance']){
          return this.$global.globalModulNameLink.enhance.url
        } else if(this.activesidebar['b2b']) {
          return this.$global.globalModulNameLink.b2b.url
        } else {
          return this.$global.globalModulNameLink.local.url
        }
      }
    },
    mounted() {
      if(this.$global.menuUserType == 'client'){
        this.activesidebar = this.$global.clientsidebar
      }else{
        this.activesidebar = this.$global.agencysidebar
  
      }
      
      this.initScrollbar();
      if (document.body.classList.contains('sidebar-mini')) {
        this.$sidebar.toggleMinimize();
      }
      if(this.isMobile){
        this.$sidebar.toggleMinimize();
      }
    },
    computed: {
      isMobile() {
        return window.innerWidth <= 768;
      }
    },
    watch: {
      '$global.isLoadingContent': {
        handler(newVal, oldVal) {
          this.$global.isLoadingContent = newVal;
        },
        deep: true,
        immediate: true
      }
    }
  };
  </script>
  <style lang="scss">
  $scaleSize: 0.95;
  @keyframes zoomIn95 {
    from {
      opacity: 0;
      transform: scale3d($scaleSize, $scaleSize, $scaleSize);
    }
    to {
      opacity: 1;
    }
  }
  
  .main-panel .zoomIn {
    animation-name: zoomIn95;
  }
  
  @keyframes zoomOut95 {
    from {
      opacity: 1;
    }
    to {
      opacity: 0;
      transform: scale3d($scaleSize, $scaleSize, $scaleSize);
    }
  }
  
  .main-panel .zoomOut {
    animation-name: zoomOut95;
  }
  
  .breadcrumb-container {
    position : absolute;
    top: 0; /* Menempel 0px dari atas container */
    z-index: 1000; /* Memastikan tetap di atas konten lain saat scroll */
    padding-top: 15px;
    padding-bottom: 15px;
    margin: -15px 0 15px 0; /* Menyesuaikan margin untuk layout yang rapi */
  }
</style>  