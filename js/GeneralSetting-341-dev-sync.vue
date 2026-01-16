<template>
    <div>

        <div class="row">
            <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                <h2><i class="fas fa-sliders-h"></i>&nbsp;&nbsp;General Setting</h2>
            </div>
        </div>
        <div class="pt-3 pb-3">&nbsp;</div>
        <div class="sticky-top general-setting-side-nav d-none d-lg-block" style="position: fixed;top: 160px; font-size: 12px;" :style="{left: $sidebar.isMinimized ?'250px':'50px'}">
              <ul class="nav flex-column">
                <li v-if="!this.$global.systemUser" class="nav-item">
                  <span class="nav-link" @click="scrollToSection('billing-plan')" :class="{ '--active': activeSection === 'billing-plan' }">Connect Your Account</span>
                </li>
                <li v-if="!this.$global.systemUser && this.$global.idsys == this.$global.masteridsys" class="nav-item">
                  <span class="nav-link" @click="scrollToSection('gohigh-level')" :class="{ '--active': activeSection === 'gohigh-level' }">Connect Your Lead Connector Account</span>
                </li>
                <li class="nav-item">
                  <span class="nav-link" @click="scrollToSection('payment-method')" :class="{ '--active': activeSection === 'payment-method' }">Default Retail Prices</span>
                </li>
             
                <li class="nav-item" v-if="!this.$global.systemUser">
                  <span class="nav-link" @click="scrollToSection('subdomain-settings')" :class="{ '--active': activeSection === 'subdomain-settings' }">Set your default subdomain</span>
                </li>
                <li class="nav-item" v-if="!this.$global.systemUser">
                  <span class="nav-link" @click="scrollToSection('white-label-domain-settings')" :class="{ '--active': activeSection === 'white-label-domain-settings' }">White Label Your Domain</span>
                </li>
                <li class="nav-item">
                  <span class="nav-link" @click="scrollToSection('color-theme')" :class="{ '--active': activeSection === 'color-theme' }">Color Theme</span>
                </li>
                <li class="nav-item">
                  <span class="nav-link" @click="scrollToSection('company-logo')" :class="{ '--active': activeSection === 'company-logo' }">Company Logo</span>
                </li>
                <li class="nav-item">
                  <span class="nav-link" @click="scrollToSection('company-product-names')" :class="{ '--active': activeSection === 'company-product-names' }">Select Your Product Names</span>
                </li>
                <li class="nav-item" v-if="!this.$global.systemUser">
                  <span class="nav-link" @click="scrollToSection('clients-default-products')" :class="{ '--active': activeSection === 'clients-default-products' }">Set Default Products for Clients</span>
                </li>
                <li class="nav-item" >
                  <span class="nav-link" @click="scrollToSection('email-settings')" :class="{ '--active': activeSection === 'email-settings' }">Email Settings</span>
                </li>
                <li class="nav-item" v-if="!this.$global.systemUser">
                  <span class="nav-link" @click="scrollToSection('email-templates')" :class="{ '--active': activeSection === 'email-templates' }">Email Templates</span>
                </li>
                <li class="nav-item">
                  <span class="nav-link" @click="scrollToSection('support-widget')" :class="{ '--active': activeSection === 'support-widget' }">Embed your support widget</span>
                </li>
                <li class="nav-item" v-if="!this.$global.systemUser">
                  <span class="nav-link" @click="scrollToSection('support-widget')" :class="{ '--active': activeSection === 'miscellaneous-settings' }">Miscellaneous settings</span>
                </li>
                <li class="nav-item" v-if="this.$global.systemUser && this.$global.idsys == this.$global.masteridsys">
                  <span class="nav-link" @click="scrollToSection('minspend-widget')" :class="{ '--active': activeSection === 'minspend-widget' }">Minimum Spend Configuration</span>
                </li>
              </ul>
            </div>
        <div class="row processingArea" v-if="!this.$global.systemUser">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
          
            <div class="col-sm-12 col-md-12 col-lg-6 text-center">
                <card>
                    <h4 class="pt-3 pb-3" v-if="!this.$global.systemUser">You need to setup / connect your Stripe <i @click="openHelpModal(3)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                    <div class="row" v-if="!this.$global.systemUser">
                            <div class="col-sm-12 col-md-12 col-lg-12 text-center" v-if="(ActionBtnConnectedAccount == 'createAccount' || ActionBtnConnectedAccount == 'createAccountLink') && userData.manual_bill == 'F'">
                                <base-button :class="statusColorConnectedAccount" :disabled="DisabledBtnConnectedAccount" size="sm" style="height:40px" id="btnCreateConnectedAccount" @click="processConnectedAccount();">
                                    <i class="fas fa-link mr-1"></i> <span v-html="txtStatusConnectedAccount">Setup your stripe account</span>
                                </base-button>
                                <div class="col-sm-12 col-md-12 col-lg-12 text-center pt-2 pb-2">OR</div>
                            </div>
                            <div class="col-sm-12 col-md-12 col-lg-12 text-center" v-if="(ActionBtnConnectedAccount == 'createAccount' || ActionBtnConnectedAccount == 'createAccountLink') && userData.manual_bill == 'F'">
                                <base-button :class="statusColorConnectedAccount" :disabled="DisabledBtnConnectedAccount" size="sm" style="height:40px" id="btnExistsConnectedAccount" @click="processExsistingConnectedAccount();">
                                    <i class="fas fa-plug mr-1"></i> <span v-html="txtStatusConnectedExistingAccount">Connect existing stripe account</span>
                                </base-button>
                            </div>
                            <div class="col-sm-12 col-md-12 col-lg-12 text-center" v-if="(ActionBtnConnectedAccount == 'inverification' || ActionBtnConnectedAccount == 'accountConnected') && userData.manual_bill == 'F'">
                                <div class="d-flex justify-content-center">
                                    <div class="d-flex">
                                        <h4 :style="{color:statusColorConnectedAccount}" v-html="txtStatusConnectedAccount">&nbsp;
                                        </h4>
                                            <i v-if="(!txtPayoutsEnabled || !txtpaymentsEnabled) && (txtErrorRequirements.length > 0)" class="fas fa-exclamation-circle ml-2" style="color: yellow; font-size: 20px; cursor: pointer;" @click="showError()"></i>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-center">
                                        <div class="d-flex align-middle mr-2">
                                            <i :class="txtpaymentsEnabled ? 'el-icon-success' : 'el-icon-error'" style="font-size: 20px; margin-right: 4px;" :style="{color: txtpaymentsEnabled ? 'green' : 'red'}"></i>
                                            <span v-if="txtpaymentsEnabled"> Payouts Enabled</span>
                                            <span v-else> Payouts Disabled</span>
                                        </div>
                                        <div class="d-flex align-middle">
                                            <i :class="txtpaymentsEnabled ? 'el-icon-success' : 'el-icon-error'" style="font-size: 20px; margin-right: 4px;" :style="{color: txtpaymentsEnabled ? 'green' : 'red'}"></i>
                                            <span v-if="txtpaymentsEnabled"> Payments Enabled</span>
                                            <span v-else> Payments Disabled</span>
                                        </div>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-12 col-lg-12 text-center" v-if="userData.manual_bill == 'T'">
                                <h4>Stripe Connection Disabled.</h4>
                            </div>
                    </div>

                    <div class="row" v-if="(!this.$global.systemUser && ActionBtnConnectedAccount == 'accountConnected' && defaultPaymentMethod == 'stripe' && plannextbill != 'free') && userData.manual_bill == 'F'" >
                        <div class="pt-3 pb-3" style="height:50px">&nbsp;</div>

                        <div class="col-sm-12 col-md-12 col-lg-12 text-center mt-4">
                            <h4>Select your plan and click save: </h4>
                        </div>
                        
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left" :class="{'disabled-area':this.radios.packageID == this.radios.freeplan}">
                            <base-radio :name="radios.nonwhitelabelling.monthly" v-model="radios.packageID" :disabled="radios.nonwhitelabelling.monthly_disabled">${{radios.nonwhitelabelling.monthlyprice}} / month - Standard Account</base-radio>
                            <base-radio :name="radios.nonwhitelabelling.yearly" v-model="radios.packageID" :disabled="radios.nonwhitelabelling.yearly_disabled">${{radios.nonwhitelabelling.yearlyprice}} / year - Standard Account</base-radio>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left" :class="{'disabled-area':this.radios.packageID == this.radios.freeplan}">
                            <base-radio :name="radios.whitelabeling.monthly" v-model="radios.packageID" :disabled="radios.whitelabeling.monthly_disabled">${{radios.whitelabeling.monthlyprice}} / month - White Labeled Account</base-radio>
                            <base-radio :name="radios.whitelabeling.yearly" v-model="radios.packageID" :disabled="radios.whitelabeling.yearly_disabled">${{radios.whitelabeling.yearlyprice}} / year - White Labeled Account</base-radio>
                        </div>
                    </div>

                    <div class="row" v-if="(!this.$global.systemUser && ActionBtnConnectedAccount == 'accountConnected' && defaultPaymentMethod != 'stripe') && userData.manual_bill == 'F'">
                        <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                            <h4>The subscription account of yours is connected to</h4>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                            <img src="https://d2uolguxr56s4e.cloudfront.net/img/shared/kartra_logo_color.svg" style="max-width:180px"/>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-12 text-center pt-4">
                                <p>&ldquo;{{packageName}}&rdquo;</p>
                        </div>
                    </div>

                    <div class="row" v-if="(defaultPaymentMethod == 'stripe' && plannextbill == 'free') && userData.manual_bill == 'F'">
                        <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                            <img src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/stripelogo.png" style="max-width:180px"/>
                        </div>
                    </div>

                    <div class="pt-3 pb-3">&nbsp;</div>

                    <div class="row" v-if="plannextbill != '' && userData.manual_bill == 'F' && plannextbill != 'free'">
                         <div class="col-sm-12 col-md-12 col-lg-12 text-left"><small>Next Billing is on : <strong>{{plannextbill}}</strong></small></div>
                    </div>

                    <div class="row" v-if="plannextbill != '' && userData.manual_bill == 'F' && plannextbill == 'free' && notPassSixtyDays">
                        <div class="col-sm-12 col-md-12 col-lg-12 text-center">For questions or cancelations, contact your account representative.</div>
                    </div>
                   
                     <div class="pt-3 pb-3">&nbsp;</div>

                    <template slot="footer" v-if="ActionBtnConnectedAccount == 'accountConnected' && userData.manual_bill == 'F'">
                        <div class="row justify-content-end" style="gap: 8px; padding-inline: 15px;">
                            <div v-if="this.$global.menuLeadsPeek_update && false">
                                <base-button type="info" round icon @click="show_helpguide('whitelabelling')">
                                <i class="fas fa-question"></i>
                                </base-button>
                                
                                
                            </div>
                            <div :class="{'disabled-area':this.radios.packageID == this.radios.freeplan && false}">
                                <el-popover
                                    content="Cancel Subscription"
                                    placement="top" 
                                    trigger="hover"
                                    v-if="this.$global.menuLeadsPeek_update && defaultPaymentMethod == 'stripe' && this.$global.rootcomp && this.$global.globalviewmode"
                                >
                                    <base-button type="danger" round icon @click="cancel_subscription()">
                                    <i class="fas fa-strikethrough"></i>
                                    </base-button>
                                </el-popover>
                            </div>
                            <div v-if="this.$global.menuLeadsPeek_update && defaultPaymentMethod == 'stripe'" :class="{'disabled-area':this.radios.packageID == this.radios.freeplan && false}">
                                <el-popover
                                    content="Reset Account Connection"
                                    placement="top"   
                                    trigger="hover"
                                >
                                    <base-button slot="reference" type="danger" round icon @click="reset_stripeconnection()">
                                    <i class="fas fa-unlink"></i>
                                    </base-button>
                                </el-popover>
                            </div>

                            <div v-if="this.$global.menuLeadsPeek_update && defaultPaymentMethod == 'stripe'" :class="{'disabled-area':this.radios.packageID == this.radios.freeplan}">
                                <base-button class="btn-green" round icon  @click="save_plan_package()" >
                                <i class="fas fa-save"></i>
                                </base-button>
                            </div>
                        </div>
                    </template>

                    <div class="col-sm-12 col-md-12 col-lg-12 text-right" ref="btnglobalreset" style="display:none" v-if="(this.$global.menuLeadsPeek_update  && defaultPaymentMethod == 'stripe') && (ActionBtnConnectedAccount != 'accountConnected' && ActionBtnConnectedAccount != 'createAccount') && userData.manual_bill == 'F'" :class="{'disabled-area':this.radios.packageID == this.radios.freeplan && false}">
                        <el-popover
                            content="Reset Account Connection"
                            placement="top"
                            trigger="hover"
                        >
                            <base-button slot="reference" type="danger" round icon @click="reset_stripeconnection()">
                            <i class="fas fa-unlink"></i>
                            </base-button>
                        </el-popover>
                    </div>
                </card>
            </div>
        </div>

         <div class="row processingArea" v-if="!this.$global.systemUser">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="billing-plan" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area': (this.radios.lastpackageID == '' && !this.$global.systemUser)}">
                <card>
                    <template slot="header">
                        <div>
                            <h4 class="d-inline">Connect Your Account <i @click="openHelpModal(0)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4> 
                        </div>
                        <h5 class="card-category">Connect your Google account to enable spreadsheet reporting ability.</h5>
                        <h5 class="card-category">Please make sure to check all permissions requested when you connect your Google account</h5>
                    </template>

                    <div class="row">
                            <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                                <base-button v-if="GoogleConnectFalse" size="sm" style="height:40px" @click="connect_googleSpreadSheet()" :disabled="!this.$global.menuLeadsPeek_update">
                                    <i class="fas fa-link"></i> Connect Google Sheet
                                </base-button>
                                <base-button v-if="GoogleConnectTrue" size="sm" style="height:40px" @click="disconnect_googleSpreadSheet()" :disabled="!this.$global.menuLeadsPeek_update">
                                    <i class="fas fa-unlink"></i> Disconnect Google Sheet
                                </base-button>
                            </div>
                    </div>
                    <div class="row pt-4">
                        <div class="col-sm-12 col-md-12 col-lg-12 pt-2 text-left">
                            <h5 class="card-category">* {{ this.$global.companyrootname }} Uses Google OAuth To Securely Access Your Google Sheets And Google Drive. By Authorizing Our App, You Allow Us To:<br/><strong>Create, Update, Write To Spreadsheets And Manage Permissions</strong></h5>
                            <h5 class="card-category">{{ this.$global.companyrootname }}'s Use And Transfer Of Any Information Received From Google APIs Will Comply With The <a href="https://developers.google.com/terms/api-services-user-data-policy#additional_requirements_for_specific_api_scopes" target="_blank">Google API Services User Data Policy</a>, Including the Limited Use Requirements.</h5>
                            <h5 class="card-category">Your Privacy And Security Are Important To Us. We Use Secure OAuth 2.0 To Access Your Data And We Do Not Store Your Google Account Credentials. You Can Revoke Our Access At Any Time From Your <a href="http://myaccount.google.com/connections" target="_blank">Google Account Settings</a> Or Click The Button Above To Disconnect.</h5>
                            <h5 class="card-category">By Using Our Services, You Agree To Let Us Access Your Google Sheets And Google Drive. You Can Revoke This Access At Any Time From Your Google Account Settings.<br/>
                                If You Have Any Questions, Please Contact Us At <a :mailto="this.$global.userrootemail" style="cursor: pointer;text-decoration: underline;">{{ this.$global.userrootemail }}</a></h5>
                        </div>
                    </div>
                </card>
            </div>
         </div>

        <div class="row processingArea" v-if="!this.$global.systemUser && this.$global.idsys == this.$global.masteridsys">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="gohigh-level" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area': (this.radios.lastpackageID == '' && !this.$global.systemUser)}">
                <card>
                    <template slot="header">
                        <div>
                            <h4 class="d-inline">Connect Your Lead Connector Account</h4> 
                        </div>
                        <h5 class="card-category" style="margin-top: 4px;">Connect your Lead Connector agency account to easily push contacts and create single sign on and custom menu links for your agency and clients</h5>
                    </template>

                    <div class="row">
                        <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                            <base-button v-if="!ghlV2Connected" size="sm" style="height:40px" @click="connectDisconnectGhlv2" :disabled="!this.$global.menuLeadsPeek_update || isConnectDisconnectGhlv2">
                                <i class="fas fa-link mr-1"></i> Connect Agency Lead Connector <i v-if="isConnectDisconnectGhlv2" class="fas fa-spinner fa-spin ml-2" style="font-size: 18px;"></i>
                            </base-button>
                            <base-button v-if="ghlV2Connected" size="sm" style="height:40px" @click="connectDisconnectGhlv2" :disabled="!this.$global.menuLeadsPeek_update || isConnectDisconnectGhlv2">
                                <i class="fas fa-unlink mr-1"></i> Disconnect Agency Lead Connector <i v-if="isConnectDisconnectGhlv2" class="fas fa-spinner fa-spin ml-2" style="font-size: 18px;"></i>
                            </base-button>
                        </div>
                        <div class="d-flex justify-content-center align-items-center mt-2 w-100">
                            <a 
                                v-if="ghlV2Connected"
                                href="#"
                                class="link-create-sub-acount-lead-connector"
                                :class="{'disabled-link-create-sub-acount-lead-connector': isGhlV2GetListSubAccountsAll}" 
                                @click.prevent="openHelpModalCreateSubAccountLeadConnector">
                                Create Client From Sub Account Lead Connector <i class="fas fa-spinner fa-spin" v-if="isGhlV2GetListSubAccountsAll" style="font-size: 13px; margin-left: 2px; margin-top: 1px"></i>
                            </a>
                        </div>
                    </div>
                </card>
            </div>
        </div>

        <div class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="payment-method" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':(!this.radios.packageID != '') && !this.$global.systemUser}" >
                <card>
                    <template slot="header">
                        <h4>Default Retail Prices <i @click="openHelpModal(4)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                        <h5 class="card-category" style="text-transform:none;">Set your default retail pricing and billing frequency for all new campaigns. <br>You can adjust individual campaign pricing and billing frequency as needed during campaign setup and later in the individual campaign's settings.</h5>
                        <!-- <h5 class="pt-4" v-if="!this.$global.systemUser">Your Wholesale cost per lead is 
                            <span v-if="defaultModule[0].name"><strong>${{ formatPrice(rootSiteIDCostPerLead) }}</strong> for {{ this.$global.globalModulNameLink.local.name }}</span>
                            <span v-if="this.$global.globalModulNameLink.enhance.name !== null && this.$global.globalModulNameLink.enhance.url !== null && defaultModule[2].name">, {{ ' ' }}</span> 
                            <span v-if="(this.$global.globalModulNameLink.enhance.name === null && this.$global.globalModulNameLink.enhance.url === null) || (!defaultModule[2].name && defaultModule[1].name && defaultModule[0].name)">and {{ ' ' }}</span>
                            <span v-if="defaultModule[1].name"><strong>${{ formatPrice(rootSearchIDCostPerLead) }}</strong> for {{ this.$global.globalModulNameLink.locator.name }} {{ ' ' }}</span>
                            <span v-if="this.$global.globalModulNameLink.enhance.name !== null && this.$global.globalModulNameLink.enhance.url !== null && defaultModule[2].name"> and 50% of retail price, minimum of <strong>${{ formatPrice(rootEnhanceIDCostPerLead) }}</strong> for {{ this.$global.globalModulNameLink.enhance.name }}</span>.
                        </h5> -->
                        <!-- <h5 class="pt-4" v-if="!this.$global.systemUser">
                            Your Wholesale cost per contact is {{ ' ' }}
                            <span v-if="defaultModule[0].name"><strong>${{ formatPrice(rootSiteIDCostPerLead) }}</strong> for {{ this.$global.globalModulNameLink.local.name }}{{ defaultModule[1].name || defaultModule[2].name ? ', ' : '.' }}</span>
                            <span v-if="defaultModule[1].name"><strong>${{ formatPrice(rootSearchIDCostPerLead) }}</strong> for {{ this.$global.globalModulNameLink.locator.name }}{{ (!defaultModule[0].name && !defaultModule[2].name) || !defaultModule[2].name ? '.' : ' ' }}</span>
                            <span v-if="this.$global.globalModulNameLink.enhance.name !== null && this.$global.globalModulNameLink.enhance.url !== null && defaultModule[2].name"> <span v-if="defaultModule[0].name || defaultModule[1].name">and</span> 50% of retail price, minimum of <strong>${{ formatPrice(rootEnhanceIDCostPerLead) }}</strong> for {{ this.$global.globalModulNameLink.enhance.name }}</span>
                            <span v-if="this.$global.globalModulNameLink.b2b.name !== null && this.$global.globalModulNameLink.b2b.url !== null && defaultModule[3].name && validateBetaFeature('b2b_module')"> <span v-if="defaultModule[0].name || defaultModule[1].name"> and</span> 50% of retail price, minimum of <strong>${{ formatPrice(rootB2bIDCostPerLead) }}</strong> for {{ this.$global.globalModulNameLink.b2b.name }}.</span>
                        </h5> -->
                        
                        <div class="pt-4" v-if="!this.$global.systemUser">
                            <h5 class="wholesale-cost-contact-title">Your Wholesale cost per contact</h5>
                            <div class="wholesale-cost-contact-list" v-if="defaultModule[0].name">
                                <h5>*</h5>
                                <h5 class="wholesale-cost-contact-message">cost per contact basic is <strong>${{ formatPrice(rootSiteIDCostPerLead) }}</strong> and cost per contact advanced is <strong>${{ formatPrice(rootSiteIDCostPerLeadAdvanced) }}</strong> 
                                    for {{ this.$global.globalModulNameLink.local.name }}.
                                </h5>
                            </div>
                            <div class="wholesale-cost-contact-list" v-if="defaultModule[1].name">
                                <h5>*</h5>
                                <h5 class="wholesale-cost-contact-message">cost per contact is <strong>${{ formatPrice(rootSearchIDCostPerLead) }}</strong> for {{ this.$global.globalModulNameLink.locator.name }}.</h5>
                            </div>
                            <div class="wholesale-cost-contact-list" v-if="this.$global.globalModulNameLink.enhance.name !== null && this.$global.globalModulNameLink.enhance.url !== null && defaultModule[2].name">
                                <h5>*</h5>
                                <h5 class="wholesale-cost-contact-message">cost per contact minimum of <strong>${{ formatPrice(rootEnhanceIDCostPerLead) }}</strong> or 50% of retail price for {{ this.$global.globalModulNameLink.enhance.name }}.</h5>
                            </div>
                            <div class="wholesale-cost-contact-list" v-if="this.$global.globalModulNameLink.b2b.name !== null && this.$global.globalModulNameLink.b2b.url !== null && defaultModule[3].name && validateBetaFeature('b2b_module')">
                                <h5>*</h5>
                                <h5 class="wholesale-cost-contact-message">cost per contact minimum of <strong>${{ formatPrice(rootB2bIDCostPerLead) }}</strong>  or 50% of retail price for {{ this.$global.globalModulNameLink.b2b.name }}.</h5>
                            </div>
                        </div>
                    </template>

                    <div v-if="defaultModule.length > 0 && defaultModule.every(item => item.name.trim() !== '')" style="border:solid 1px;opacity:0.5;margin-top:24px;">&nbsp;</div>

                    <div class="d-flex align-items-center my-4" style="flex-wrap: wrap;">
                        <template>
                            <div @click="activePriceSettingTab = 1" class="pricing-setting-item-toggle" :class="{'--active': activePriceSettingTab === 1}" v-if="defaultModule[0].name">
                                <h5 v-html="this.$global.globalModulNameLink.local.name" style="text-transform:uppercase;font-weight:bold">:&nbsp;</h5>
                            </div> 
                            <div @click="activePriceSettingTab = 2" class="pricing-setting-item-toggle" :class="{'--active': activePriceSettingTab === 2}" v-if="defaultModule[1].name">
                                <h5 v-html="this.$global.globalModulNameLink.locator.name" style="text-transform:uppercase;font-weight:bold">&nbsp;</h5>
                            </div>
                            <div v-if="this.$global.globalModulNameLink.enhance.name !== null && this.$global.globalModulNameLink.enhance.url != null && defaultModule[2].name" @click="activePriceSettingTab = 3" class="pricing-setting-item-toggle" :class="{'--active': activePriceSettingTab === 3}">
                                <h5 v-html="this.$global.globalModulNameLink.enhance.name" style="text-transform:uppercase;font-weight:bold">&nbsp;</h5>
                            </div>
                            <div v-if="this.$global.globalModulNameLink.b2b.name !== null && this.$global.globalModulNameLink.b2b.url != null && defaultModule[3].name && validateBetaFeature('b2b_module')" @click="activePriceSettingTab = 4" class="pricing-setting-item-toggle" :class="{'--active': activePriceSettingTab === 4}">
                                <h5 v-html="this.$global.globalModulNameLink.b2b.name" style="text-transform:uppercase;font-weight:bold">&nbsp;</h5>
                            </div>
                            <div v-if="this.$global.globalModulNameLink.simplifi.name !== null && this.$global.globalModulNameLink.simplifi.url != null && defaultModule[4].name && !this.$global.systemUser && this.$global.idsys == this.$global.masteridsys && validateBetaFeature('simplifi_module')" @click="activePriceSettingTab = 5" class="pricing-setting-item-toggle" :class="{'--active': activePriceSettingTab === 5}">
                                <h5 v-html="this.$global.globalModulNameLink.simplifi.name" style="text-transform:uppercase;font-weight:bold">&nbsp;</h5>
                            </div>
                            <div v-if="this.$global.systemUser && this.$global.idsys == this.$global.masteridsys" @click="activePriceSettingTab = 6" class="pricing-setting-item-toggle" :class="{'--active': activePriceSettingTab === 6}">
                                <h5 style="text-transform:uppercase;font-weight:bold">Clean ID</h5>
                            </div>
                        </template>
                        <div
                            v-if="Object.values({...$global.agencysidebar, simplifi: $global.idsys != $global.masteridsys ? false : $global.agencysidebar.simplifi}).length > 0 && Object.values({...$global.agencysidebar,simplifi: $global.idsys != $global.masteridsys ? false : $global.agencysidebar.simplifi}).every(item => item === false)"
                            style="text-align: center; margin: auto; display: flex; align-items: center; justify-content: center; height: 200px;">
                            <p>Currently, no products are available</p>
                        </div>
                    </div>

                    <!-- LOCAL -->
                    <div v-show="activePriceSettingTab === 1" v-if="defaultModule[0].name">
                        <div v-if="selectsAppModule.AppModuleSelect == 'LeadsPeek'">
                            <div class="row">
                                <div class="col-sm-12 col-md-6 col-lg-12 col-xl-6">
                                    <small style="display: flex; margin-bottom: 4px;">One time setup fee</small>
                                    <base-input
                                        type="text"
                                        placeholder="0"
                                        addon-left-icon="fas fa-dollar-sign"
                                        v-model="LeadspeekPlatformFee"    
                                        @keyup="set_fee('local','LeadspeekPlatformFee');"
                                        @blur="handleFormatCurrency('local','LeadspeekPlatformFee')"
                                        @keydown="restrictInput"    
                                        @copy.prevent @cut.prevent @paste.prevent
                                    >
                                    </base-input>
                                    <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for One Time Creative/Set Up Fee is $<span>{{formatPrice(m_LeadspeekPlatformFee)}}</span></span></div>
                                </div>
                                <div class="col-sm-12 col-md-6 col-lg-12 col-xl-6">
                                    <small style="display: flex; margin-bottom: 4px;">{{txtLeadService.charAt(0).toUpperCase() + txtLeadService.slice(1)}} campaign fee</small>
                                    <base-input
                                        type="text"
                                        placeholder="0"
                                        addon-left-icon="fas fa-dollar-sign"  
                                        v-model="LeadspeekMinCostMonth"    
                                        @keyup="set_fee('local','LeadspeekMinCostMonth');"
                                        @blur="handleFormatCurrency('local','LeadspeekMinCostMonth')"
                                        @keydown="restrictInput"    
                                        @copy.prevent @cut.prevent @paste.prevent  
                                    >
                                    </base-input>
                                    <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for charging your client for Platform Fee is $<span>{{formatPrice(m_LeadspeekMinCostMonth)}}</span></span></div>
                                </div>
                            </div>
                            <div class="row mt-sm-0 mt-md-3 mt-lg-0 mt-xl-3">
                                <div class="col-sm-12 col-md-6 col-lg-12 col-xl-6">
                                    <small style="display: flex; margin-bottom: 4px;">
                                        Cost per contact (basic)
                                        <sup>
                                            <el-popover
                                                trigger="hover"
                                                :content="helpContentMap[10].desc"
                                                effect="light"
                                                placement="top-start"
                                                width="80"
                                                popper-class="tooltip-content"
                                            >
                                                <span style="font-size: 12px;" slot="reference">
                                                    <i class="fa fa-question-circle"></i>
                                                </span>
                                            </el-popover>
                                        </sup>
                                        <!-- <sup>
                                            <span style="cursor: pointer; margin-left: 3px; font-size: 12px;"><i @click="openHelpModal(10)" class="fa fa-question-circle"></i></span>
                                        </sup> -->
                                    </small>
                                    <base-input
                                    v-if="selectsPaymentTerm.PaymentTermSelect != 'One Time'"
                                                type="text"
                                                placeholder="0"
                                                addon-left-icon="fas fa-dollar-sign"
                                                v-model="LeadspeekCostperlead"    
                                                @keyup="set_fee('local','LeadspeekCostperlead');"
                                                @blur="handleFormatCurrency('local','LeadspeekCostperlead')"
                                                @keydown="restrictInput"   
                                                @copy.prevent @cut.prevent @paste.prevent
                                            >
                                    </base-input>
                                    <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for cost per contact $<span>{{formatPrice(rootSiteIDCostPerLead)}}</span></span></div>
                                </div>
                                <div class="col-sm-12 col-md-6 col-lg-12 col-xl-6">
                                    <small style="display: flex; margin-bottom: 4px;">
                                        Cost per contact (Advanced)
                                        <sup>
                                            <el-popover
                                                trigger="hover"
                                                :content="helpContentMap[11].desc"
                                                effect="light"
                                                placement="top"
                                                popper-class="tooltip-content"
                                            >
                                            <span slot="reference" style="font-size: 12px;">
                                                <i class="fa fa-question-circle"></i>
                                            </span>
                                            </el-popover>
                                        </sup>
                                        <!-- <sup>
                                            <span style="cursor: pointer; margin-left: 3px; font-size: 12px;"><i @click="openHelpModal(11)" class="fa fa-question-circle"></i></span>
                                        </sup> -->
                                    </small>
                                    <base-input type="text" placeholder="0" addon-left-icon="fas fa-dollar-sign"
                                        v-model="LeadspeekCostperleadAdvanced"
                                        @keyup="set_fee('local','LeadspeekCostperleadAdvanced');"
                                        @blur="handleFormatCurrency('local','LeadspeekCostperleadAdvanced')"
                                        @keydown="restrictInput"  
                                        @copy.prevent @cut.prevent @paste.prevent>
                                    </base-input>
                                    <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your advanced price for cost per contact $<span>{{formatPrice(rootSiteIDCostPerLeadAdvanced)}}</span></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- LOCAL -->

                    <!-- LOCATOR -->
                    <div v-show="activePriceSettingTab === 2" v-if="defaultModule[1].name">
                        <div class="row" v-if="selectsAppModule.AppModuleSelect == 'LeadsPeek'">
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">One time setup fee</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="LocatorPlatformFee"    
                                    @keyup="set_fee('locator','LocatorPlatformFee');"
                                    @blur="handleFormatCurrency('locator','LocatorPlatformFee')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for One Time Creative/Set Up Fee is $<span>{{formatPrice(m_LeadspeekLocatorPlatformFee)}}</span></span></div>
                            </div>

                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">{{ txtLeadService.charAt(0).toUpperCase() + txtLeadService.slice(1) }} campaign fee</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="LocatorMinCostMonth"    
                                    @keyup="set_fee('locator','LocatorMinCostMonth');"
                                    @blur="handleFormatCurrency('locator','LocatorMinCostMonth')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for charging your client for Platform Fee is $<span>{{formatPrice(m_LeadspeekLocatorMinCostMonth)}}</span></span></div>
                            </div>
                            
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">Cost per contact</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="lead_FirstName_LastName_MailingAddress_Phone"    
                                    @keyup="set_fee('locatorlead','FirstName_LastName_MailingAddress_Phone');"
                                    @blur="handleFormatCurrency('locatorlead','FirstName_LastName_MailingAddress_Phone')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for cost per contact $<span>{{formatPrice(rootSearchIDCostPerLead)}}</span></span></div>
                            </div>
                        </div>
                    </div>
                    <!-- LOCATOR -->

                    <!-- ENHANCE -->
                    <div v-if="this.$global.globalModulNameLink.enhance.name !== null && this.$global.globalModulNameLink.enhance.url != null && defaultModule[2].name" v-show="activePriceSettingTab === 3">
                        <div class="row" v-if="selectsAppModule.AppModuleSelect == 'LeadsPeek'">
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">One time setup fee</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="EnhancePlatformFee"    
                                    @keyup="set_fee('enhance','EnhancePlatformFee');"
                                    @blur="handleFormatCurrency('enhance','EnhancePlatformFee')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for One Time Creative/Set Up Fee is $<span>{{formatPrice(m_LeadspeekEnhancePlatformFee)}}</span></span></div>
                            </div>
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">{{ txtLeadService.charAt(0).toUpperCase() + txtLeadService.slice(1) }} campaign fee</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="EnhanceMinCostMonth"    
                                    @keyup="set_fee('enhance','EnhanceMinCostMonth');"
                                    @blur="handleFormatCurrency('enhance','EnhanceMinCostMonth')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for charging your client for Platform Fee is $<span>{{formatPrice(m_LeadspeekEnhanceMinCostMonth)}}</span></span></div>
                            </div>
                            <!-- <div class="flex-grow-1 price-setting-form-item" >
                                <base-input
                                    label="Cost per lead"
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="lead_FirstName_LastName_MailingAddress_Phone"    
                                    style="width:120px"      
                                    @keyup="set_fee('locatorlead','FirstName_LastName_MailingAddress_Phone');"   
                                >
                                </base-input>
                            </div> -->
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <!-- :label="`cost per lead ${txtLeadOver ? txtLeadOver : 'from the monthly charge'}?`" -->
                                <small style="display: flex; margin-bottom: 4px;">Cost per contact</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="EnhanceCostperlead"    
                                    @keyup="set_fee('enhance','EnhanceCostperlead');"
                                    @blur="handleFormatCurrency('enhance','EnhanceCostperlead')"
                                    @keydown="restrictInput"    
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for cost per contact $<span>{{formatPrice(rootEnhanceIDCostPerLead)}}</span></span></div>
                            </div>
                        </div>
                    </div>
                    <!-- ENHANCE -->

                    <!-- B2B -->
                    <div v-if="this.$global.globalModulNameLink.b2b.name !== null && this.$global.globalModulNameLink.b2b.url != null && defaultModule[3].name && validateBetaFeature('b2b_module')" v-show="activePriceSettingTab === 4">
                        <div class="row" v-if="selectsAppModule.AppModuleSelect == 'LeadsPeek'">
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">One time setup fee</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="B2bPlatformFee"    
                                    @keyup="set_fee('b2b','B2bPlatformFee');"
                                    @blur="handleFormatCurrency('b2b','B2bPlatformFee')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for One Time Creative/Set Up Fee is $<span>{{formatPrice(m_LeadspeekB2BPlatformFee)}}</span></span></div>
                            </div>
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">{{ txtLeadService.charAt(0).toUpperCase() + txtLeadService.slice(1) }} campaign fee</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="B2bMinCostMonth"    
                                    @keyup="set_fee('b2b','B2bMinCostMonth');"
                                    @blur="handleFormatCurrency('b2b','B2bMinCostMonth')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for charging your client for Platform Fee is $<span>{{formatPrice(m_LeadspeekB2BMinCostMonth)}}</span></span></div>
                            </div>
                            <!-- <div class="flex-grow-1 price-setting-form-item" >
                                <base-input
                                    label="Cost per lead"
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="lead_FirstName_LastName_MailingAddress_Phone"    
                                    style="width:120px"      
                                    @keyup="set_fee('locatorlead','FirstName_LastName_MailingAddress_Phone');"   
                                >
                                </base-input>
                            </div> -->
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <!-- :label="`cost per lead ${txtLeadOver ? txtLeadOver : 'from the monthly charge'}?`" -->
                                <small style="display: flex; margin-bottom: 4px;">Cost per contact</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="B2bCostperlead"    
                                    @keyup="set_fee('b2b','B2bCostperlead');"
                                    @blur="handleFormatCurrency('b2b','B2bCostperlead')"
                                    @keydown="restrictInput"    
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                                <div v-if="!this.$global.systemUser" class="default-price-helper-text"><span>Your base price for cost per contact $<span>{{formatPrice(rootB2bIDCostPerLead)}}</span></span></div>
                            </div>
                        </div>
                    </div>
                    <!-- B2B -->

                    <!-- SIMPLFI -->
                    <div v-if="this.$global.globalModulNameLink.simplifi.name !== null && this.$global.globalModulNameLink.simplifi.url != null && defaultModule[4].name && !this.$global.systemUser && this.$global.idsys == this.$global.masteridsys && validateBetaFeature('simplifi_module')" v-show="activePriceSettingTab === 5">
                        <div class="row" v-if="selectsAppModule.AppModuleSelect == 'LeadsPeek'">
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">Max Bid</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="SimplifiMaxBid"    
                                    @keyup="set_fee('simplifi','SimplifiMaxBid');"
                                    @blur="handleFormatCurrency('simplifi','SimplifiMaxBid')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent>
                                    <template #addonRight>
                                        <span class="input-group-text cpm-remove-border">CPM</span>
                                    </template>
                                </base-input>
                                <span v-if="simplifiErrorInput.maxBid" v-html="simplifiErrorInput.maxBid" class="error-message-price-simplifi"></span>
                            </div>
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">Daily Budget</small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="SimplifiDailyBudget"    
                                    @keyup="set_fee('simplifi','SimplifiDailyBudget');"
                                    @blur="handleFormatCurrency('simplifi','SimplifiDailyBudget')"
                                    @keydown="restrictInput"   
                                    @copy.prevent @cut.prevent @paste.prevent>
                                </base-input>
                                <span v-if="simplifiErrorInput.dailyBudget" v-html="simplifiErrorInput.dailyBudget" class="error-message-price-simplifi"></span>
                            </div>
                            <div class="col-sm-12 col-md-4 col-lg-12 col-xl-4">
                                <small style="display: flex; margin-bottom: 4px;">Agency Profit Margin</small>

                                <el-select style="width: 100%;" class="select-primary" size="large" placeholder="Select Modules" v-model="SimplifiAgencyMarkup" @change="set_fee('simplifi','SimplifiAgencyMarkup');">
                                    <el-option v-for="markup in agencyMarkup.list" :value="markup.value" :label="markup.text" :key="markup.value"></el-option>
                                </el-select>
                            </div>
                        </div>
                    </div>
                    <!-- SIMPLFI -->

                    <!-- CLEAN ID -->
                    <div v-if="this.$global.systemUser && this.$global.idsys == this.$global.masteridsys" v-show="activePriceSettingTab === 6">
                        <div class="row" v-if="selectsAppModule.AppModuleSelect == 'LeadsPeek'">
                            <div class="col-12">
                                <small style="display: flex; margin-bottom: 4px;">
                                    Cost per contact (Advanced)
                                    <sup>
                                        <el-popover
                                            trigger="hover"
                                            :content="helpContentMap[12].desc"
                                            effect="light"
                                            placement="top"
                                            popper-class="tooltip-content"
                                        >
                                        <span slot="reference" style="font-size: 12px;">
                                            <i class="fa fa-question-circle"></i>
                                        </span>
                                        </el-popover>
                                    </sup>
                                </small>
                                <base-input
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="CleanCostperleadAdvanced"    
                                    @keyup="set_fee('clean','CleanCostperleadAdvanced');"
                                    @blur="handleFormatCurrency('clean','CleanCostperleadAdvanced')"
                                    @keydown="restrictInput"    
                                    @copy.prevent @cut.prevent @paste.prevent
                                >
                                </base-input>
                            </div>
                        </div>
                    </div>
                    <!-- CLEAN ID -->

                    <div class="pricing-duration-dropdown-wrapper mt-4" v-if="![5, 6].includes(activePriceSettingTab)">
                        <!-- <div  class="col-sm-12 col-md-12 col-lg-12 text-center">
                            <h5>Please choose the default billing cycle for your agency.</h5>
                        </div> -->
                        <label>Billing Frequency</label>
                        <el-select
                            class="select-primary"
                            size="small"
                            placeholder="Select Modules"
                            v-model="selectsPaymentTerm.PaymentTermSelect"
                            @change="paymentTermChange()"
                            >
                            <el-option
                                v-for="option in selectsPaymentTerm.PaymentTerm"
                                class="select-primary"
                                :value="option.value"
                                :label="option.label"
                                :key="option.label"
                            >
                            </el-option>
                        </el-select>
                    </div>
                        <div v-show="this.$global.systemUser === false">
                            <base-checkbox v-model="enabledDeletedAccountClient" class="pull-left">Enable the delete client account menu</base-checkbox>
                        </div>
                    <div v-if="defaultModule.length > 0 && defaultModule.every(item => item.name.trim() !== '')" style="border:solid 1px;opacity:0.5;margin-top:24px;">&nbsp;</div>
                    <!-- temp remove -->
                    <!-- <div class="row pt-3">
                        <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                            <h5>Please choose your default price settings for {{ this.$global.globalModulNameLink.locator.name}}</h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12 col-md-12 col-lg-12" v-if="false">
                            <h5 class="d-inline pr-3" style="float:left;line-height:40px">
                            &#x2022;&nbsp;Emails and Names
                            </h5>
                            <div class="d-inline" style="float:left;">
                                <base-input
                                    label=""
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="lead_FirstName_LastName"    
                                    style="width:120px"      
                                    @keyup="set_fee('locatorlead','FirstName_LastName');"   
                                >
                                </base-input>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-12" v-if="false">
                            <h5 class="d-inline pr-3" style="float:left;line-height:40px">
                            &#x2022;&nbsp;Emails, Names, and Mailing Addresses
                            </h5>
                            <div class="d-inline" style="float:left;">
                                <base-input
                                    label=""
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign" 
                                    v-model="lead_FirstName_LastName_MailingAddress"    
                                    style="width:120px"      
                                    @keyup="set_fee('locatorlead','FirstName_LastName_MailingAddress');"   
                                >
                                </base-input>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-12" >
                            <h5 class="d-inline pr-3" style="float:left;line-height:40px">
                            Default Price per lead
                            </h5>
                            <div class="d-inline" style="float:left;">
                                <base-input
                                    label=""
                                    type="text"
                                    placeholder="0"
                                    addon-left-icon="fas fa-dollar-sign"
                                    v-model="lead_FirstName_LastName_MailingAddress_Phone"    
                                    style="width:120px"      
                                    @keyup="set_fee('locatorlead','FirstName_LastName_MailingAddress_Phone');"   
                                >
                                </base-input>
                            </div>
                        </div>
                    </div> -->
                    <!-- temp remove -->

                    <template slot="footer" v-if="ActionBtnConnectedAccount == 'accountConnected' || this.$global.systemUser || userData.manual_bill == 'T'">
                        <div class="row pull-right">
                            <div class="col-sm-6 col-md-6 col-lg-6 text-right" v-if="this.$global.menuLeadsPeek_update && false">
                                <base-button type="info" round icon @click="show_helpguide('defaultprice')">
                                <i class="fas fa-question"></i>
                                </base-button>
                                
                                
                            </div>
                            <div class="col-sm-6 col-md-6 col-lg-6 text-left" v-if="this.$global.menuLeadsPeek_update">
                                <base-button :disabled="isLoadingDefaultRetailPrices" class="btn-green" round :icon="isLoadingDefaultRetailPrices ? false : true"  @click="save_default_price()" >
                                <i class="fas fa-save"></i> {{ isLoadingDefaultRetailPrices ? 'Saving...' : '' }}
                                </base-button>
                            </div>
                        </div>
                    </template>
                </card>
            </div>
         </div>

         <div class="row processingArea" v-if="!this.$global.systemUser">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="subdomain-settings" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':(!this.radios.packageID != '')}" v-if="!domainSetupCompleted || !Whitelabellingstatus">
                <card>
                    <template slot="header">
                        <h4>Set your default subdomain</h4>
                        <h5 class="card-category" style="text-transform:none;">This is your default subdomain, you can change this to your own domain or subdomain if you choose the white labeling plan by entering it below under "White Labeling Options".</h5>
                    </template>
                   
                    <div class="row pt-3">
                            <div class="col-sm-4 col-md-4 col-lg-4 pr-0 mr-0">
                            <base-input
                                label=""
                                type="text"
                                placeholder="yoursubdomain"
                                v-model="DownlineSubDomain"
                                inline
                                >
                            </base-input>
                            
                          </div>
                          <div class="col-sm-5 col-md-5 col-lg-5 ml-0 pl-2 text-left" style="padding-top:10px;">
                            .{{$global.companyrootdomain.toLowerCase()}}
                          </div>
                            <div class="col-sm-3 col-md-3 col-lg-3">
                               &nbsp;
                            </div>
                    </div>

                    <template slot="footer" v-if="ActionBtnConnectedAccount == 'accountConnected' || userData.manual_bill == 'T'">
                        <div class="row pull-right">
                            <div class="col-sm-6 col-md-6 col-lg-6 text-right" v-if="this.$global.menuLeadsPeek_update && false">
                                <base-button type="info" round icon @click="show_helpguide('setdefaultsubdomain')">
                                <i class="fas fa-question"></i>
                                </base-button>
                                
                                
                            </div>
                            <div class="col-sm-6 col-md-6 col-lg-6 text-left" v-if="this.$global.menuLeadsPeek_update">
                                <base-button class="btn-green" round icon  @click="save_default_subdomain()" >
                                <i class="fas fa-save"></i>
                                </base-button>
                            </div>
                        </div>
                    </template>

                </card>
            </div>
        </div>

        <div class="row processingArea" v-if="!this.$global.systemUser">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="white-label-domain-settings" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':(is_whitelabeling == 'F' || !this.radios.packageID != '')}">
                <card>
                    <template slot="header">
                        <h4>White Label Your Domain<i @click="openHelpModal(2)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                        <h5 class="card-category">Use your own domain to White Label the system and customize the service product names.</h5>
                    </template>
                    <div class="pt-3 pb-3">&nbsp;</div>
                    <ul class="text-left list-unstyled d-flex flex-column" style="padding-left: 0px;gap:8px ">
                        <li><span style="font-weight:700;">Step 1:</span> Point your A record to our IP address using the settings found at this <a href="javascript:void(0);" @click="modals.whitelabelingguide = true">link</a>.</li>

                        <li><span style="font-weight:700;">Step 2:</span> Verify that your A record is correctly pointed by<a href=" https://dnschecker.org/#A/" target="_blank"> clicking here</a>, Putting in your personal domain, and verifying that it has been propagated to our IP address. This may take up to 12 hours to fully propagate.</li>

                       <li><span style="font-weight:700;">Step 3:</span> After confirming that your DNS settings are correct and fully propagated, enter your personal URL here and be sure to click Save.</li>
                    </ul>
                     <div class="row">
                        <div class="col-sm-10 col-md-10 col-lg-10 form-group">
                            <label class="col-form-label pull-left pr-2">Domain / subdomain Name:</label>
                            <base-input
                                type="text"
                                placeholder="yourdomain.com"
                                addon-left-icon="fas fa-globe-americas"
                                v-model="DownlineDomain"
                                id="dwdomain"
                                >
                            </base-input>

                            <!-- <small class="pull-left text-left">*You need to set your domain host to our server, <a href="javascript:void(0);" @click="modals.whitelabelingguide = true">click here for more information</a></small> -->
                            <div class="pull-left d-line" v-if="DownlineDomain != '' && DownlineDomainStatus != ''">
                                <small class="pull-left">* Domain Status : <span v-html="DownlineDomainStatus"></span></small>
                                <div v-if="userData.status_domain !== 'ssl_acquired' && userData.status_domain !== 'action_retry' && userData.status_domain !== ''" class="mt-2 pull-left">
                                    <base-button 
                                        type="secondary" 
                                        size="sm" 
                                        @click="showDomainRetryConfirmation"
                                        class="btn-sm"
                                        :disabled="isRetryingDomainSSL"
                                        style="background-color: #6c757d; border-color: #6c757d; color: white;"
                                    >
                                        <i v-if="!isRetryingDomainSSL" class="fas fa-redo-alt mr-1"></i>
                                        <i v-else class="fas fa-spinner fa-spin mr-1"></i>
                                        {{ isRetryingDomainSSL ? 'Reconfiguring...' : 'Reconfigure Domain SSL Certificate' }}
                                    </base-button>
                                </div>
                            </div>

                        </div>
                        <div class="col-sm-2 col-md-2 col-lg-2">&nbsp;</div>

                        <div class="col-sm-12 col-md-12 col-lg-12">
                            <base-checkbox v-model="chkagreewl" :class="{'has-danger': agreewhitelabelling}" class="pull-left" v-if="false">
                                I agree with the white labelling term and condition and enabled this feature
                            </base-checkbox>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-12 pt-5" v-if="false">
                            <small><em>* Full White Labeling is an additional $100 a month.<br/>All Options Below will be customizable with full white labeling</em></small><br>
                        </div>
                     </div>
                     
                     <template slot="footer">
                        <div class="row pull-right">
                            <div class="col-sm-6 col-md-6 col-lg-6 text-right" v-if="this.$global.menuLeadsPeek_update && false">
                                <base-button type="info" round icon @click="show_helpguide('whitelabelling')">
                                    <i class="fas fa-question"></i>
                                </base-button>
                                
                                
                            </div>
                            <div class="col-sm-6 col-md-6 col-lg-6 text-left" v-if="this.$global.menuLeadsPeek_update">
                                <base-button :disabled="isLoadingWhiteLabelingDomain" class="btn-green" round :icon="isLoadingWhiteLabelingDomain ? false : true" @click="save_general_whitelabelling()">
                                <i class="fas fa-save"></i>  {{ isLoadingWhiteLabelingDomain ? 'Saving...' : '' }}
                                </base-button>
                            </div>
                        </div>
                    </template>

                </card>
            </div>

        </div>
 
        <div class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="color-theme" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':((is_whitelabeling == 'F' || !this.radios.packageID != '') && !this.$global.systemUser)}">
                <card id="themecolor" style="width:100%">
                    <template slot="header">
                        <h4>Select Your Color Palette</h4>
                    </template>

                    
                    <div class="row">
                        <label class="col-sm-4 col-md-4 col-lg-4 col-form-label">Sidebar Background Color:</label>
                            <div class="col-sm-4 col-md-4 col-lg-4 d-inline-flex" style="justify-content: center;">
                                <!-- <input class="form-control" id="sidebarcolor" type="text" value="" /><a class="pl-2 pt-2" href="javascript:void(0);" @click="reverthistory('sidebar');"><i class="fas fa-history"></i></a> -->
                                 <div style="display: flex;">
                                     <el-color-picker v-model="colors.sidebar" @change="onSidebarChange" @active-change="onSidebarActiveChange"></el-color-picker>
                                     <a class="pl-2 pt-2" href="javascript:void(0);" @click="reverthistory('sidebar');"><i class="fas fa-history"></i></a>
                                 </div>
                            </div>
                            <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>    
                        
                    </div>
                    <!-- <div v-if="false" class="row">
                        <label class="col-sm-4 col-md-4 col-lg-4 col-form-label">Template Background Color:</label>
                            <div class="col-sm-4 col-md-4 col-lg-4 d-inline-flex pt-2">
                                <input class="form-control" id="backgroundtemplatecolor" type="text" value="" /><a class="pl-2 pt-2" href="javascript:void(0);" @click="reverthistory('template');"><i class="fas fa-history"></i></a>
                            </div>
                            <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>    
                        
                    </div>
                    <div v-if="false" class="row">
                        <label class="col-sm-4 col-md-4 col-lg-4 col-form-label">Box Background Color:</label>
                            <div class="col-sm-4 col-md-4 col-lg-4 d-inline-flex pt-2">
                                <input class="form-control" id="boxcolor" type="text" value="" /><a class="pl-2 pt-2" href="javascript:void(0);" @click="reverthistory('box');"><i class="fas fa-history"></i></a>
                            </div>
                            <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>    
                        
                    </div> -->
                    <div class="row pt-3" >
                        <label class="col-sm-4 col-md-4 col-lg-4 col-form-label">Text Color:</label>
                            <div class="col-sm-4 col-md-4 col-lg-4 d-inline-flex" style="justify-content: center;">
                                <!-- <input class="form-control" id="textcolor" type="text" value="" /><a class="pl-2 pt-2" href="javascript:void(0);" @click="reverthistory('text');"><i class="fas fa-history"></i></a> -->
                                <div style="display: flex;">
                                     <el-color-picker v-model="colors.text" @change="onTextColorChange" @active-change="onTextActiveChange"></el-color-picker>
                                     <a class="pl-2 pt-2" href="javascript:void(0);" @click="reverthistory('text');"><i class="fas fa-history"></i></a>
                                 </div>
                            </div>
                            <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>    
                        
                    </div>
                    <div v-if="false" class="row">
                        <label class="col-sm-4 col-md-4 col-lg-4 col-form-label">Link Color:</label>
                            <div class="col-sm-4 col-md-4 col-lg-4 d-inline-flex pt-2">
                                <input class="form-control" id="linkcolor" type="text" value="" /><a class="pl-2 pt-2" href="javascript:void(0);" @click="reverthistory('link');"><i class="fas fa-history"></i></a>
                            </div>
                            <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>    
                        
                    </div>
                    <template slot="footer">
                        <div class="row pull-right">
                            <div class="col-sm-6 col-md-6 col-lg-6 text-right" v-if="this.$global.menuLeadsPeek_update && false">
                                <base-button type="info" round icon @click="show_helpguide('colorthemeinfo')">
                                <i class="fas fa-question"></i>
                                </base-button>
                                
                                
                            </div>
                            <div class="col-sm-6 col-md-6 col-lg-6 text-left" v-if="this.$global.menuLeadsPeek_update">
                                <base-button :disabled="isLoadingColorPalete" class="btn-green" round :icon="isLoadingColorPalete ? false : true"  @click="save_general_colortheme()">
                                   <i class="fas fa-save"></i> {{ isLoadingColorPalete ? 'Saving...' : '' }}
                                </base-button>
                            </div>
                        </div>
                    </template>
                   <!--<div class="pull-left p-2">
                        <div style="width:120px;border:4px green solid;">
                                <div class="btn-primary" style="height:20px;width:100%">&nbsp;</div>
                                <div style="height:20px;width:100%;background-color:#FFF">&nbsp;</div>
                                <div style="height:20px;width:100%;background-color:#1e1e2f">&nbsp;</div>
                        </div>
                   </div>

                   <div class="pull-left p-2">
                        <div style="width:120px;border:2px #FFF solid;">
                                <div style="height:20px;width:100%;background-color:#344675">&nbsp;</div>
                                <div style="height:20px;width:100%;background-color:#FFF">&nbsp;</div>
                                <div style="height:20px;width:100%;background-color:#1e1e2f">&nbsp;</div>
                        </div>
                   </div>

                    <div class="pull-left p-2">
                        <div style="width:120px;border:2px #FFF solid;">
                                <div style="height:20px;width:100%;background-color:#ff8d72">&nbsp;</div>
                                <div style="height:20px;width:100%;background-color:#344675">&nbsp;</div>
                                <div style="height:20px;width:100%;background-color:#1e1e2f">&nbsp;</div>
                        </div>
                   </div>

                   <div class="pull-left p-2">
                        <div style="width:120px;border:2px #FFF solid;">
                                <div style="height:20px;width:100%;background-color:#f4f5f7">&nbsp;</div>
                                <div style="height:20px;width:100%;background-color:#42b883">&nbsp;</div>
                                <div style="height:20px;width:100%;background-color:#942434">&nbsp;</div>
                        </div>
                   </div>-->
                           
                </card>
            </div>

            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>

        <div class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':((is_whitelabeling == 'F' || !this.radios.packageID != '') && !this.$global.systemUser)}">
                <card id="themecolor">
                    <template slot="header">
                        <h4>Select Your Preferred Font</h4>
                    </template>

                    <div style="display:inline-block;margin:0 auto">
                        <div class="row">
                                <div class="pull-left p-2" style="cursor:pointer">
                                    <div class="fontoption" id="Poppins" style="width:120px;border:4px #FFF solid;" @click="changefont('Poppins',$event);">
                                            <div style="height:60px;width:100%;background-color:white;color:black;padding:14px;font-family:'sans-serif';font-size:0.95em;">Sans Serif Font</div>
                                    </div>
                                </div>
                                
                                <div class="pull-left p-2" style="cursor:pointer">
                                        <div class="fontoption" id="nucleo" style="width:120px;border:4px #FFF solid;" @click="changefont('nucleo',$event);">
                                                <div style="height:60px;width:100%;background-color:white;color:black;padding:14px;font-family:'nucleo';font-size:0.95em;">Nucleo Font</div>
                                        </div>
                                </div>

                                    <div class="pull-left p-2" style="cursor:pointer">
                                        <div class="fontoption" id="Montserrat" style="width:120px;border:4px #FFF solid;" @click="changefont('Montserrat',$event);">
                                                <div style="height:60px;width:100%;background-color:white;color:black;padding:14px;font-family:'Montserrat';font-size:0.95em;">Montserrat Font</div>
                                        </div>
                                </div>

                                <div class="pull-left p-2" style="cursor:pointer">
                                        <div class="fontoption" id="Helvetica Neue" style="width:120px;border:4px #FFF solid;" @click="changefont('Helvetica Neue',$event);">
                                            <div style="height:60px;width:100%;background-color:white;color:black;padding:10px;font-family:'Helvetica Neue';font-size:0.95em;">Helvetica Neue Font</div>
                                        </div>
                                </div>
                        </div>

                        <div class="row">
                            
                            <div class="pull-left p-2" style="cursor:pointer">
                                    <div class="fontoption" id="Arial" style="width:120px;border:4px  #FFF solid;" @click="changefont('Arial',$event);">
                                            <div style="height:60px;width:100%;background-color:white;color:black;padding:14px;font-family:'Arial';font-size:0.95em;">Arial Font</div>
                                    </div>
                            </div>

                            <div class="pull-left p-2" style="cursor:pointer">
                                    <div class="fontoption" id="Courier New" style="width:120px;border:4px #FFF solid;" @click="changefont('Courier New',$event);">
                                            <div style="height:60px;width:100%;background-color:white;color:black;padding:10px;font-family:'Courier New';font-size:0.95em;">Courier New Font</div>
                                    </div>
                            </div>

                                <div class="pull-left p-2" style="cursor:pointer">
                                    <div class="fontoption" id="monospace" style="width:120px;border:4px #FFF solid;" @click="changefont('monospace',$event);">
                                            <div style="height:60px;width:100%;background-color:white;color:black;padding:14px;font-family:'monospace';font-size:0.95em;">Monospace Font</div>
                                    </div>
                            </div>

                            <div class="pull-left p-2" style="cursor:pointer">
                                    <div class="fontoption" id="courier" style="width:120px;border:4px #FFF solid;" @click="changefont('courier',$event);">
                                        <div style="height:60px;width:100%;background-color:white;color:black;padding:10px;font-family:'courier';font-size:0.95em;">Courier Font</div>
                                    </div>
                            </div>
                        </div>
                    </div>

                   <template slot="footer">
                        <div class="row pull-right">
                            <div class="col-sm-6 col-md-6 col-lg-6 text-right" v-if="this.$global.menuLeadsPeek_update && false">
                                <base-button type="info" round icon @click="show_helpguide('fontthemeinfo')">
                                <i class="fas fa-question"></i>
                                </base-button>
                                
                                
                            </div>
                            <div class="col-sm-6 col-md-6 col-lg-6 text-left" v-if="this.$global.menuLeadsPeek_update">
                                <base-button :disabled="isLoadingFont" class="btn-green" round :icon="isLoadingFont ? false : true" @click="save_general_fontheme()">
                                <i class="fas fa-save"></i> {{ isLoadingFont ? 'Saving...' : '' }}
                                </base-button>
                            </div>
                        </div>
                    </template>
                           
                </card>
            </div>

            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>

        <div class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
            <div id="company-logo" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':((is_whitelabeling == 'F' || !this.radios.packageID != '') && !this.$global.systemUser)}">
                <card id="themecolor">
                    <template slot="header">
                        <h4>Customize Your Logo <i @click="openHelpModal(5)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                        <br>Supported file type jpeg,jpg,png,gif. Max file size is 1080kb <br>recommended dimensions 120x120
                    </template>

                    <div class="row mt-2">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-center" style="padding-block: 8px;">
                            <div>
                                <label>Login / Register Logo</label>
                            </div>
                            <div style="height:120px;display: flex;align-items: center;justify-content: center">
                                <img :src="logo.loginAndRegister" alt="logo login and register" style="max-width: 100%;max-height: 100%;" />
                            </div>
                            <div class="pt-2" id="progressmsgshow3" style="display:none">
                                <div class="progress mt-3" style="height: 5px">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; height: 100%">0%</div>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-12 pt-2" id="progressmsg">
                                    <label style="color:#942434">* Please wait while your image is being uploaded. This may take a few minutes.</label>
                                </div>
                            </div>
                            <div class="pt-2">
                                <button id="browseFileLogoLoginAndRegister" ref="browseFileLogoLoginAndRegister" class="btn btn-simple btn-file">Update Logo</button>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-center" style="padding-block: 8px;">
                            <div>
                                <label>Sidebar Menu Logo</label>
                            </div>
                            <div style="height:120px;display: flex;align-items: center;justify-content: center">
                                <img :src="logo.sidebar" alt="logo sidebar" style="max-width: 100%;max-height: 100%;" />
                            </div>
                            <div class="pt-2" id="progressmsgshow4" style="display:none">
                                <div class="progress mt-3" style="height: 5px">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; height: 100%">0%</div>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-12 pt-2" id="progressmsg">
                                    <label style="color:#942434">* Please wait while your image uploads. (It might take a couple of minutes.)</label>
                                </div>
                            </div>
                            <div class="pt-2">
                                <button  id="browseFileLogoSidebar" ref="browseFileLogoSidebar" class="btn btn-simple btn-file">Update Logo</button>
                            </div>
                        </div>
                    </div>
                </card>
            </div>
        </div>

        <div class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':((is_whitelabeling == 'F' || !this.radios.packageID != '') && !this.$global.systemUser)}">
                <card id="themecolor">
                    <template slot="header">
                        <h4>Customize Your Images <i @click="openHelpModal(5)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                        <br>Supported file type jpeg,jpg,png,gif. Max file size is 1080kb <br>recommended dimensions 460x720
                    </template>
                   
                    <div class="row">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-center" style="padding-block: 8px; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <label>Login Image</label>
                                <img :src="images.login" alt="image login" style="max-width: 100%; max-height: 100%; margin-top: 4px;" />
                            </div>
                            <div class="text-center">
                                <div class="pt-2" id="progressmsgshow" style="display:none">
                                    <div class="progress mt-3" style="height: 5px">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; height: 100%">0%</div>
                                    </div>
                                    <div class="col-sm-12 col-md-12 col-lg-12 pt-2" id="progressmsg">
                                        <label style="color:#942434">* Please wait while your image uploads. (It might take a couple of minutes.)</label>
                                    </div>
                                </div>
                                <div class="pt-2">
                                    <button id="browseFileLogin" ref="browseFileLogin" class="btn btn-simple btn-file">Update Image</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-center" v-if="!this.$global.systemUser" style="padding-block: 8px; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <label>Register Image</label>
                                <img :src="images.register" alt="image register" style="max-width: 100%; max-height: 100%; margin-top: 4px;" />
                            </div>
                            <div class="text-center">
                                <div class="pt-2" id="progressmsgshow1" style="display:none">
                                    <div class="progress mt-3" style="height: 5px">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; height: 100%">0%</div>
                                    </div>
                                    <div class="col-sm-12 col-md-12 col-lg-12 pt-2" id="progressmsg">
                                        <label style="color:#942434">* Please wait while your image uploads. (It might take a couple of minutes.)</label>
                                    </div>
                                </div>
                                <div class="pt-2">
                                    <button id="browseFileRegister" ref="browseFileRegister" class="btn btn-simple btn-file">Update Image</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-center" v-if="this.$global.systemUser" style="padding-block: 8px; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <label>Agency Register Image</label>
                                <img :src="images.agency" alt="image agency" style="max-width: 100%; max-height: 100%; margin-top: 4px;" />
                            </div>
                            <div class="text-center">
                                <div class="pt-2" id="progressmsgshow2" style="display:none">
                                    <div class="progress mt-3" style="height: 5px">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; height: 100%">0%</div>
                                    </div>
                                    <div class="col-sm-12 col-md-12 col-lg-12 pt-2" id="progressmsg">
                                        <label style="color:#942434">* Please wait while your image uploads. (It might take a couple of minutes.)</label>
                                    </div>
                                </div>
                                <div class="pt-2">
                                    <button id="browseFileAgency" ref="browseFileAgency" class="btn btn-simple btn-file">Update Image</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </card>
            </div>

            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>

        <div class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="company-product-names" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':((is_whitelabeling == 'F' || !this.radios.packageID != '') && !this.$global.systemUser)}">
                <card>
                    <template slot="header">
                        <h4>Select Your Product Names <i @click="openHelpModal(6)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                        <h5 class="card-category">Rename Your Product Offerings</h5>
                    </template>
                     <template>
                         <div class="pt-3 pb-3" v-if="defaultModule[0].name">&nbsp;</div>

                    <div class="row" v-if="defaultModule[0].name">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.local.name + ' Module Name:'"
                                placeholder="Enter New Module Name"
                                v-model="leadsLocalName"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.local.name + ' Module URL:'"
                                placeholder="Enter URL Path ex. siteid"
                                v-model="leadsLocalUrl"
                                maxLength="256"
                                @change="leadsLocalUrl = leadsLocalUrl.toLowerCase()"
                            >
                            </base-input>
                        </div>
                    </div>
                    <div class="pt-3 pb-3" v-if="defaultModule[1].name">&nbsp;</div>
                    
                    <div class="row" v-if="defaultModule[1].name">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.locator.name + ' Module Name:'"
                                placeholder="Enter New Module Name"
                                value = "Search ID"
                                v-model="leadsLocatorName"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.locator.name + ' Module URL:'"
                                placeholder="Enter URL Path ex. searchid"
                                v-model="leadsLocatorUrl"
                                maxLength="256"
                                @change="leadsLocatorUrl = leadsLocatorUrl.toLowerCase()"
                            >
                            </base-input>
                        </div>
                    </div>

                    <div v-if="this.$global.globalModulNameLink.enhance.name !== null && this.$global.globalModulNameLink.enhance.url !== null && defaultModule[2].name" class="pt-3 pb-3">&nbsp;</div>
                    <div v-if="this.$global.globalModulNameLink.enhance.name !== null && this.$global.globalModulNameLink.enhance.url !== null && defaultModule[2].name" class="row">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.enhance.name + ' Module Name:'"
                                placeholder="Enter New Module Name"
                                value = "Enhance ID"
                                v-model="leadsEnhanceName"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.enhance.name + ' Module URL:'"
                                placeholder="Enter URL Path ex. searchid"
                                v-model="leadsEnhanceUrl"
                                @change="leadsEnhanceUrl = leadsEnhanceUrl.toLowerCase()"
                            >
                            </base-input>
                        </div>
                    </div>

                    <div v-if="this.$global.globalModulNameLink.b2b.name !== null && this.$global.globalModulNameLink.b2b.url !== null && defaultModule[3].name && validateBetaFeature('b2b_module')" class="pt-3 pb-3">&nbsp;</div>
                    <div v-if="this.$global.globalModulNameLink.b2b.name !== null && this.$global.globalModulNameLink.b2b.url !== null && defaultModule[3].name && validateBetaFeature('b2b_module')" class="row">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.b2b.name + ' Module Name:'"
                                placeholder="Enter New Module Name"
                                value = "B2B ID"
                                v-model="leadsB2bName"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.b2b.name + ' Module URL:'"
                                placeholder="Enter URL Path ex. searchid"
                                v-model="leadsB2bUrl"
                                maxLength="256"
                                @change="leadsB2bUrl = leadsB2bUrl.toLowerCase()"
                            >
                            </base-input>
                        </div>
                    </div>

                    <div v-if="this.$global.globalModulNameLink.simplifi.name !== null && this.$global.globalModulNameLink.simplifi.url !== null && defaultModule[4].name && validateBetaFeature('simplifi_module') && this.$global.idsys == this.$global.masteridsys" class="pt-3 pb-3">&nbsp;</div>
                    <div class="row" v-if="this.$global.globalModulNameLink.simplifi.name !== null && this.$global.globalModulNameLink.simplifi.url !== null && defaultModule[4].name && validateBetaFeature('simplifi_module') && this.$global.idsys == this.$global.masteridsys">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.simplifi.name + ' Module Name:'"
                                placeholder="Enter New Module Name"
                                value = "Simplifi ID"
                                v-model="leadsSimplifiName"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                :label= "this.$global.globalModulNameLink.simplifi.name + ' Module URL:'"
                                placeholder="Enter URL Path ex. searchid"
                                v-model="leadsSimplifiUrl"
                                maxLength="256"
                                @change="leadsSimplifiUrl = leadsSimplifiUrl.toLowerCase()"
                            >
                            </base-input>
                        </div>
                    </div>
                 </template>

                     <div
                        v-if="Object.values({...$global.agencysidebar, simplifi: $global.idsys != $global.masteridsys ? false : $global.agencysidebar.simplifi}).length > 0 && Object.values({...$global.agencysidebar,simplifi: $global.idsys != $global.masteridsys ? false : $global.agencysidebar.simplifi}).every(item => item === false)"
                        style="text-align: center; margin: auto; display: flex; align-items: center; justify-content: center; height: 200px;">
                        <p>Currently, no products are available</p>
                    </div>
                   

                    <template slot="footer">
                        <div class="row pull-right">
                            <div class="col-sm-6 col-md-6 col-lg-6 text-right" v-if="this.$global.menuLeadsPeek_update && false">
                                <base-button type="info" round icon @click="show_helpguide('custommoduleinfo')">
                                <i class="fas fa-question"></i>
                                </base-button>
                                
                                
                            </div>
                            <div class="col-sm-6 col-md-6 col-lg-6 text-left" v-if="this.$global.menuLeadsPeek_update">
                                <base-button :disabled="isLoadingCostumeModule" class="btn-green" round :icon="isLoadingCostumeModule ? false : true" @click="save_general_custommenumodule()">
                                <i class="fas fa-save"></i> {{ isLoadingCostumeModule ? 'Saving...' : '' }}
                                </base-button>
                            </div>
                        </div>
                    </template>

                </card>
            </div>

            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>
        <div class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
            <div id="clients-default-products" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':((is_whitelabeling == 'F' || !this.radios.packageID != '') && !this.$global.systemUser)}">
               <card>
                    <template slot="header">
                        <h4 v-if="this.$global.systemUser">Set Default Products for Agencies</h4>
                        <h4 v-if="!this.$global.systemUser">Set Default Products for Clients <i @click="openHelpModal(7)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                        <h5 class="card-category" v-if="this.$global.systemUser">New Agencies will automatically have the selected default products assigned upon creation.</h5>
                        <h5 class="card-category" v-if="!this.$global.systemUser">New clients will automatically have the selected default products assigned upon creation.</h5>
                    </template>
                    <div class="pt-3 pb-3">&nbsp;</div>

                    <div class="row" style="justify-content: space-between; align-items: center;">
                         <template>
                            <div 
                            v-for="(item, index) in defaultModule" 
                            :key="index" 
                            v-show="item.name && validateBetaFeature(item.type) && (item.type != 'simplifi' || $global.idsys == $global.masteridsys)" 
                            @click="handleDefaultModule(item.type, !item.status)" 
                            style="flex: 1; cursor: pointer; padding-block: 8px; padding-left: 8px; padding-right: 8px;">
                            <div class="product__default__module" :class="[item.status ? 'active__default__module' : '']">
                                <i :class="[item.icon, item.status ? 'active__default__module__text' : 'default__module__text']" style="font-size: 18px;"></i>
                                <span style="margin-top: 4px;" :class="[item.status ? 'active__default__module__text' : 'default__module__text']">
                                    {{ item.name }}
                                </span>
                            </div>
                        </div>
                        </template>
                         <div
                            v-if="Object.values({...$global.agencysidebar, simplifi: $global.idsys != $global.masteridsys ? false : $global.agencysidebar.simplifi}).length > 0 && Object.values({...$global.agencysidebar,simplifi: $global.idsys != $global.masteridsys ? false : $global.agencysidebar.simplifi}).every(item => item === false)"
                            style="text-align: center; margin: auto; display: flex; align-items: center; justify-content: center; height: 200px;">
                            <p>Currently, no products are available</p>
                        </div>

                        <div class="col-12" style="display: flex; justify-content: flex-end; margin-top: 32px;">
                            <base-button :disabled="isLoadingSaveDefaultModule" class="btn-green" round :icon="isLoadingSaveDefaultModule ? false : true" @click="saveDefaultModule">
                                <i class="fas fa-save"></i> {{ isLoadingSaveDefaultModule ? 'Saving...' : '' }}
                            </base-button>
                        </div>
                    </div>
                </card>
            </div>
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>

        <div class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="email-settings" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':((is_whitelabeling == 'F' || !this.radios.packageID != '') && !this.$global.systemUser)}">
                <card id="st_smtp">
                    <template slot="header">
                        <h4>Email Settings<i @click="openHelpModal(1)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                        <h5 class="card-category">By default, outbound emails to Administrators and Customers use the email address of {{ this.$global.companyrootname }}. To customize the sending email address, connect your email service provider below and UNCHECK "Use Default Email SMTP".</h5>
                    </template>
                    <div class="pt-3 pb-3">&nbsp;</div>
                    <div class="row">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                label="Email Host"
                                placeholder="ex. smtp.gmail.com"
                                v-model="customsmtp.host"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                label="Port Number"
                                placeholder="ex. 465"
                                v-model="customsmtp.port"
                                @input="validatePort"
                            >
                            </base-input>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                label="Username"
                                placeholder="ex. youremail@gmail.com"
                                v-model="customsmtp.username"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left">
                            <base-input
                                label="App Password"
                                placeholder="ex. mypassword"
                                :type="showPassword ? 'text' : 'password'"
                                 v-model="customsmtp.password"
                            >
                            </base-input>
                             <button
                                    type="button"
                                    class="icon-password"
                                    @click="togglePassword"
                                    >
                                    <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                </button>
                            <p style="font-size: 12px; cursor: pointer;" @click="showModalAppPassword"><span style="opacity: 0.8;">How to generate an App Password? </span><a style="color: #4286f4;">Learn More</a></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12 col-md-12 col-lg-12 text-left pt-2" >
                            <div class="d-inline"><label>Security Protocol:</label></div>
                            <base-radio name="none" v-model="customsmtp.security" class="d-inline pl-2">None</base-radio>
                            <base-radio name="ssl" v-model="customsmtp.security"  class="d-inline pl-2">SSL</base-radio>
                            <base-radio name="tls" v-model="customsmtp.security"  class="d-inline pl-2">TLS</base-radio>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-12 col-md-12 col-lg-12 text-left pt-2">
                            <div>
                                <base-radio :name="true" v-model="customsmtp.default" :class="{ 'radio-inactive': !customsmtp.default }">Send using {{$global.companyrootname}} email address.</base-radio>
                            </div>
                            <div>
                                <base-radio :name="false" v-model="customsmtp.default" :class="{ 'radio-inactive': customsmtp.default }">Send using {{$global.globalCompanyName}} email address.</base-radio>
                            </div>
                        </div>
                    </div>

                
                      <template slot="footer">
                        <div class="row">
                            <div class="col-sm-6 col-md-6 col-lg-6 text-left" v-if="this.$global.menuLeadsPeek_update">
                                <!--<base-button type="info" round icon @click="show_helpguide('smtpemailinfo')">
                                <i class="fas fa-question"></i>
                                </base-button>-->
                                <base-button :disabled="isSendingTestSMTP" class="btn-green" @click="test_smtpemail()" v-if="!customsmtp.default">
                                {{btnTestSMTP}}
                                </base-button>
                                
                            </div>
                            <div class="col-sm-6 col-md-6 col-lg-6 text-right" v-if="this.$global.menuLeadsPeek_update">
                                <base-button :disabled="isLoadingEmailSettings" round :icon="isLoadingEmailSettings ? false : true" class="btn-green" @click="save_general_smtpemail()">
                                     <i class="fas fa-save"></i> {{ isLoadingEmailSettings ? 'Saving...' : '' }}
                                </base-button>
                            </div>
                        </div>
                    </template>
                           
                </card>
            </div>

            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>
        
        <div class="row processingArea" v-if="!this.$global.systemUser">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>

            <div id="email-templates" class="col-sm-12 col-md-12 col-lg-6 text-center" :class="{'disabled-area':(is_whitelabeling == 'F' || !this.radios.packageID != '')}">
                <card>
                    <template slot="header">
                        <h4>Email Templates <i @click="openHelpModal(8)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px;"></i></h4>
                        <h5 class="card-category">Below are all of the outbound email templates that will be sent to your clients and administrators.<br/>Click on any template to customize it.</h5>
                    </template>
                    <div class="pt-3 pb-3">&nbsp;</div>
                    <div class="row">
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left email-template-item">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><span class="cursor-pointer" @click="get_email_template('forgetpassword');">Forgot Password</span> 
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left email-template-item">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><span class="cursor-pointer" @click="get_email_template('clientwelcome');">Account Created</span> 
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left email-template-item">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><span class="cursor-pointer" @click="get_email_template('campaigncreated');">Campaign Created</span> 
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left email-template-item">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><span class="cursor-pointer" @click="get_email_template('billingunsuccessful');">Billing Unsuccessful</span> 
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left email-template-item">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><span class="cursor-pointer" @click="get_email_template('archivecampaign');">Campaign Archived</span> 
                        </div>
                        <div class="col-sm-6 col-md-6 col-lg-6 text-left email-template-item">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><span class="cursor-pointer" @click="get_email_template('prepaidtopuptwodaylimitclient');">Campaign Prepaid Limit</span>
                        </div>
                        <!--<div class="col-sm-4 col-md-4 col-lg-4 text-left">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><a href="#" @click="get_email_template('agencyclientwelcome');">Agency account setup email</a> 
                        </div>-->
                    </div>
                    <div class="row pt-2">
                        <!--<div class="col-sm-4 col-md-4 col-lg-4 text-left">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><a href="">Questionairre Result Email</a>
                        </div>
                        <div class="col-sm-4 col-md-4 col-lg-4 text-left">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><a href="">Leads Local Embedded Code Email</a>
                        </div>
                        <div class="col-sm-4 col-md-4 col-lg-4 text-left">
                             <i class="fas fa-circle pr-2" style="font-size:11px"></i><a href="">Leads Locator Embedded Code Email</a>
                        </div>-->
                    </div>
                    
                    <template slot="footer">
                        <div>&nbsp;</div>
                    </template>
                </card>
            </div>

            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>

        <div id="support-widget" class="row processingArea">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
            <div class="col-sm-12 col-md-12 col-lg-6 text-center">
                <card>
                    <template slot="header">
                        <h4>Embed your support widget <i @click="openHelpModal(9)" class="fa fa-question-circle" style="cursor: pointer; margin-left: 12px; "></i></h4>
                        <h5 class="card-category">You may embed your support widget by inserting the embed code below. We recommend adjusting your widget settings so it appears in the lower right corner.</h5>
                    </template>
                    <div class="pt-3 pb-3">&nbsp;</div>
                    <div class="row">
                        <div class="col-sm-12 col-md-12 col-lg-12">
                            <div class="form-group has-label">
                                                        
                                <textarea
                                    id="agencyEmbeddedCode"
                                    class="form-control"
                                    v-model="agencyEmbeddedCode.embeddedcode"
                                    placeholder="" 
                                    rows="5"
                                    style="border:solid 1px;"
                                >
                                </textarea>
                                                        
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-12 pt-2 text-left">
                            <label class="pl-2">Place Code on :</label>
                                <el-select
                                    class="select-primary pl-2"
                                    size="small"
                                    placeholder="Select Modules"
                                    v-model="agencyEmbeddedCode.placeEmbedded"
                                    >
                                    <el-option
                                        v-for="option in selectsPlaceEmbeddedCode.PlaceEmbededCodeList"
                                        class="select-primary"
                                        :value="option.value"
                                        :label="option.label"
                                        :key="option.label"
                                    >
                                    </el-option>
                                </el-select>
                          </div>
                        
                    </div>
                    <template slot="footer">
                        <div class="row">
                            <div class="col-sm-12 col-md-12 col-lg-12 text-right" v-if="this.$global.menuLeadsPeek_update">
                                <base-button :disabled="isLoadingEmbeddedSupportWidget" class="btn-green" round :icon="isLoadingEmbeddedSupportWidget ? false : true" @click="save_general_agencyembeddedcode()">
                                <i class="fas fa-save"></i> {{ isLoadingEmbeddedSupportWidget ? 'Saving...' : '' }}
                                </base-button>
                            </div>
                        </div>
                    </template>
                </card>
                <card v-if="this.$global.systemUser === false">
                    <template slot="header" id="miscellaneous-settings">
                        <h4>Miscellaneous Settings</h4>
                        <h5 class="card-category">General options for managing optional system features</h5>
                    </template>

                    <div class="pt-3 pb-3">&nbsp;</div>

                    <div class="row">
                        <div class="col-sm-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <base-checkbox v-model="enabledDeletedAccountClient">
                                    Enable the delete client account menu
                                </base-checkbox>
                            </div>
                        </div>
                    </div>

                    <template slot="footer">
                        <div class="row">
                            <div class="col-sm-12 col-md-12 col-lg-12 text-right" v-if="this.$global.menuLeadsPeek_update">
                                <base-button 
                                    :disabled="isLoadingDeleteClientStatus"
                                    class="btn-green"
                                    round
                                    :icon="isLoadingDeleteClientStatus ? false : true"
                                    @click="save_client_isDeleteStatus()"
                                >
                                    <i class="fas fa-save"></i>
                                    {{ isLoadingDeleteClientStatus ? 'Saving...' : '' }}
                                </base-button>
                            </div>
                        </div>
                    </template>
                </card>
            </div>
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>

        <div id="minspend-widget" class="row processingArea" v-if="this.$global.systemUser && this.$global.idsys == this.$global.masteridsys">
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
            <div class="col-sm-12 col-md-12 col-lg-6 text-center">
                <card>
                    <modal :show.sync="modals.mindSpendConfig">
                        <el-card shadow="never">
                            <h4 class="color_minimum_spend_config" style="text-align: center; color: black !important;">Minimum Spend Configuration</h4>
                            <div style="margin-bottom: 30px;">
                                <label>Plan Name</label>
                                <base-input class="campaign-cost-input mb-0" max="1" v-model="planName" placeholder="Enter Plan Name" @keyup="keyupInputMinimumSpend('add', 'plan_name')" />
                                <small v-if="minimumspendError.planName" style="color:#942434 !important;">* Plan Name is required</small>
                            </div>
    
                            <el-divider></el-divider>
    
                            <label>Monthly Spend List</label> 
                            <div v-for="(month, index) in months" :key="index" style="margin-bottom: 10px; display: flex; flex-direction: column;">
                                <div style="display: flex; gap: 8px;">
                                    <base-input
                                        addon-left-icon="fas fa-dollar-sign"
                                        class="campaign-cost-input mb-0"  
                                        type="text"
                                        style="flex: 1;"
                                        v-model="months[index]"
                                        :placeholder="`$ Month ${index + 1}`"
                                        @keyup.enter="addMonth('add')"
                                        @keyup="keyupInputMinimumSpend('add', 'months', index)"
                                        @keydown="restrictInput"
                                        required
                                        @copy.prevent @cut.prevent @paste.prevent/>
                                    <!-- v-if="months.length > 1" -->
                                    <el-button
                                        type="danger"
                                        icon="el-icon-delete"
                                        style="border-radius: 0.4285rem; padding: 10px 18px; height: calc(2.33571rem + 4px);"
                                        @click="removeMonth('add', index)"
                                        >
                                    </el-button>
                                </div>
                                <small v-if="minimumspendError.monthlySpendList[index]" style="color:#942434 !important; margin-top: 2px;">* Spend Monthly is required</small>
                            </div>
                            <base-button 
                                @click="addMonth('add')"
                                :disabled="months.length >= monthMaxLengh"
                                style="height: 40px; width: 100%; margin-bottom: 10px;">
                                <i class="fas fa-plus-circle" style="margin-right: 4px;"></i> Add Month
                            </base-button>
    
                            <el-divider></el-divider>
    
                            <div>
                                <label>Flat Monthly Spend</label>
                                <base-input
                                    addon-left-icon="fas fa-dollar-sign"
                                    class="campaign-cost-input mb-0"  
                                    type="text"
                                    v-model="flatMonth"
                                    placeholder="$ Flat Month"
                                    style="flex: 1"
                                    @keyup="keyupInputMinimumSpend('add', 'flat_month')"
                                    @keydown="restrictInput"
                                    required
                                    @copy.prevent @cut.prevent @paste.prevent/>
                                <small v-if="minimumspendError.flatMonth" style="color:#942434 !important;">* Flat Monthly is required</small>
                            </div>
                            <div class="text-right">
                                <base-button class="pull-right mt-3 mb-4" :disabled="isLoadingAddEditMinimumSpend" style="height:40px;" @click="processAddEditMinimumSpendList('')">
                                    Save
                                    <i v-if="isLoadingAddEditMinimumSpend" class="fas fa-spinner fa-spin ml-1" style="font-size: 16px;"></i>
                                </base-button>
                            </div>
                        </el-card>
                    </modal>
                    <modal :show.sync="modals.mindSpendConfigEdit">
                        <el-card shadow="never">
                            <h4 class="color_minimum_spend_config" style="text-align: center; color: black !important;">Minimum Spend Configuration</h4>
                            <div style="margin-bottom: 30px;">
                                <label>Plan Name</label>
                                <base-input class="campaign-cost-input mb-0" max="1" v-model="planNameEdit" placeholder="Enter Plan Name" @keyup="keyupInputMinimumSpend('edit', 'plan_name')" />
                                <small v-if="minimumspendErrorEdit.planName" style="color:#942434 !important;">* Plan Name is required</small>
                            </div>
    
                            <el-divider></el-divider>
    
                            <label>Monthly Spend List</label>
                            <div v-for="(month, index) in monthsEdit" :key="index" style="margin-bottom: 10px; display: flex; flex-direction: column;">
                                <div style="display: flex; gap: 8px;">
                                    <base-input
                                        addon-left-icon="fas fa-dollar-sign"
                                        class="campaign-cost-input mb-0"  
                                        type="text"
                                        style="flex: 1;"
                                        v-model="monthsEdit[index]"
                                        :placeholder="`$ Month ${index + 1}`"
                                        @keyup.enter="addMonth('edit')"
                                        @keyup="keyupInputMinimumSpend('edit', 'months', index)"
                                        @keydown="restrictInput"
                                        required
                                        @copy.prevent @cut.prevent @paste.prevent/>
                                    <el-button
                                        type="danger"
                                        icon="el-icon-delete"
                                        style="border-radius: 0.4285rem; padding: 10px 18px; height: calc(2.33571rem + 4px);"
                                        @click="removeMonth('edit', index)"
                                        >
                                    </el-button>
                                </div>
                                <small v-if="minimumspendErrorEdit.monthlySpendList[index]" style="color:#942434 !important; margin-top: 2px;">* Spend Monthly is required</small>
                            </div>
                            <base-button 
                                @click="addMonth('edit')"
                                :disabled="monthsEdit.length >= monthMaxLengh"
                                style="height: 40px; width: 100%; margin-bottom: 10px;">
                                <i class="fas fa-plus-circle" style="margin-right: 4px;"></i> Add Month
                            </base-button>
    
                            <el-divider></el-divider>
    
                            <div>
                                <label>Flat Monthly Spend</label>
                                <base-input
                                    addon-left-icon="fas fa-dollar-sign"
                                    class="campaign-cost-input mb-0"  
                                    type="text"
                                    v-model="flatMonthEdit"
                                    placeholder="$ Flat Month"
                                    style="flex: 1"
                                    @keyup="keyupInputMinimumSpend('edit', 'flat_month')"
                                    @keydown="restrictInput"
                                    required
                                    @copy.prevent @cut.prevent @paste.prevent/>
                                <small v-if="minimumspendErrorEdit.flatMonth" style="color:#942434 !important;">* Flat Monthly is required</small>
                            </div>
                            <div class="text-right">
                                <base-button class="pull-right mt-3 mb-4" :disabled="isLoadingAddEditMinimumSpend" style="height:40px;" @click="processAddEditMinimumSpendList(idMinimumSpendEdit)">
                                    Update
                                    <i v-if="isLoadingAddEditMinimumSpend" class="fas fa-spinner fa-spin ml-1" style="font-size: 16px;"></i>
                                </base-button>
                            </div>
                        </el-card>
                    </modal>
    
                    <template slot="header">
                        <div class="d-flex justify-content-center">
                            <h4 style="font-size: large;">Minimum Spend Configuration</h4>
                            <i class="fa fa-question-circle mx-2"></i>
                        </div>
                        <h5 class="card-category">Set the required minimum monthly spend for your agency. The system will track and ensure this target is met each month.</h5>
                    </template>
    
                    <div v-if="isLoadingFetchMinimumSpend" class="py-5 text-center">
                        <i class="fas fa-spinner fa-spin ml-1 mb-3" style="font-size: 24px;"></i>
                        <p style="font-size: 13px;">Loading Data...</p>
                    </div>
                    <div v-else>
                        <div style="margin-top: 50px; margin-bottom: 50px;" v-if="minimumSpendLists.length === 0 || !minimumSpendLists">
                            <small>Minimum Spend Configuration is empty</small>
                        </div>
                        <card class="active__default__module" :class="{'minimum-spend-is-active': item.isDefault}" style="cursor: pointer;" v-for="(item) in minimumSpendLists" :key="item.id">
                            <div @click="setDefaultMinimumSpend(item.id)">
                                <div>
                                    <span v-if="item.isDefault" class="icon-default-minspend">Default</span>
                                    <p style="font-weight: bold; text-align: left; margin-bottom: 30px">{{ item.planName }}</p>
                                </div>
                                <div>
                                    <div style="display: flex; justify-content: center; align-items: center; width: 100%; margin: auto;">
                                        <el-steps :active="item.months.length" style="width: 100%;" align-center>
                                            <el-step v-for="(month,  index) in item.months" :key="index" :title="`Month ${index + 1}`" :description="`$` + month"></el-step>
                                            <el-step status="wait" :title="`Flat Month`" :description="`$` + item.flatMonth"></el-step>
                                        </el-steps>
                                    </div>
                                    <div style="display: flex; justify-content: end; margin: 8px; margin-top: 12px;">
                                        <button v-if="item.id != 'system'" class="btn btn-green btn-round btn-icon btn-fab btn-default mx-1" type="button" style="width: 1px;" @click.stop="openHelpModalEditMinSpendModal(item)">
                                            <i class="tim-icons icon-pencil"></i>   
                                        </button>
                                        <button v-if="item.id != 'system'" class="btn btn-green btn-round btn-icon btn-fab btn-default mx-1" type="button" style="width: 1px;" @click.stop="processDeleteMinimumSpendList(item.id)">
                                            <i class="el-icon-delete"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </card>
                    </div>

                    <div class="d-flex justify-content-end mt-2 mb-3">
                        <div>
                            <p class="card-category">
                                Showing {{ paginationMinimumSpend.from }} to {{ paginationMinimumSpend.to }} of {{ paginationMinimumSpend.total }} entries
                            </p>
                            <base-pagination 
                                class="pagination-no-border mt-3"
                                v-model="paginationMinimumSpend.currentPage"
                                :per-page="paginationMinimumSpend.perPage"
                                :total="paginationMinimumSpend.total"
                                @input="changePageMinimumSpend">
                            </base-pagination>
                        </div>
                    </div>
    
                    <div class="d-flex justify-content-end">
                        <base-button class="my-2" @click="openHelpModalMinSpendModal()">
                            <i class="fas fa-plus-circle" style="margin: 0 4px 1px 0;"></i>
                            Add Configuration
                        </base-button>
                    </div>
                </card>
            </div>
            <div class="col-sm-0 col-md-0 col-lg-3">&nbsp;</div>
        </div>

        
        <!-- MODAL DOMAIN/SUBDOMAIN WHITELABELING TEMPLATES-->
            <modal :show.sync="modals.whitelabelingguide" headerClasses="justify-content-center">
                <h4 slot="header" class="title title-up">Whitelabeling URL instructions</h4>
                <div class="row">
                    <div class="col-sm-12 col-md-12 col-lg-12 text-left">
                       <p>To have full white labeling of your URL. Point your URL A Record to <strong><em>157.230.213.72</em></strong></p>
                       <p>For more information on how to do this, Please see the following help articles. Also feel free to reach out to:</p>
                       <p>
                        - <a href="https://www.godaddy.com/help/add-an-a-record-19238" target="_blank">https://www.godaddy.com/help/add-an-a-record-19238</a><br/>
                        - <a href="https://www.bluehost.com/help/article/dns-management-add-edit-or-delete-dns-entries" target="_blank">https://www.bluehost.com/help/article/dns-management-add-edit-or-delete-dns-entries</a><br/>
                        - <a href="https://support.squarespace.com/hc/en-us/articles/360002101888-Adding-DNS-records-to-your-domain" target="_blank">https://support.squarespace.com/hc/en-us/articles/360002101888-Adding-DNS-records-to-your-domain</a><br/>
                        - <a href="https://www.siteground.com/kb/manage-dns-records" target="_blank">https://www.siteground.com/kb/manage-dns-records</a><br/>

                       </p>
                    </div>
                </div>
                
                <template slot="footer">
                    <div class="container text-center pb-4">
                    <base-button @click="modals.whitelabelingguide = false">Close</base-button>
                    </div>
                </template>
            </modal>
        <!-- MODAL DOMAIN/SUBDOMAIN WHITELABELING TEMPLATES-->

        <!-- MODAL DOMAIN SSL RETRY CONFIRMATION-->
            <modal :show.sync="modals.domainRetryConfirmation" headerClasses="justify-content-center" style="z-index: 9999;">
                <h4 slot="header" class="title title-up" style="padding: 20px 0; margin: 0; font-size: 1.5rem; font-weight: 600;">Confirm Domain Configuration</h4>
                <div class="row">
                    <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                        <div class="alert alert-info" style="background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px;">
                            <i class="fas fa-exclamation-triangle fa-2x mb-3" style="color: #0c5460;"></i>
                            <h5 style="color: #0c5460 !important; font-weight: 600;">Domain SSL Certificate Reconfiguration</h5>
                        </div>
                        <p class="text-left" style="color: #495057; font-weight: 500;">
                            Before proceeding with SSL certificate reconfiguration, please confirm that you have:
                        </p>
                        <ul class="text-left" style="color: #495057; padding-left: 20px;">
                            <li style="margin-bottom: 8px;color: #495057;"><strong>Pointed your A record</strong> to our server IP address: <code style="background-color: #f8f9fa; padding: 2px 6px; border-radius: 4px; color: #e74c3c; font-weight: 600;">157.230.213.72</code></li>
                            <li style="margin-bottom: 8px;color: #495057;"><strong>Verified the DNS propagation</strong> using a DNS checker tool</li>
                            <li style="margin-bottom: 8px;color: #495057;"><strong>Waited for full propagation</strong> (up to 12 hours)</li>
                        </ul>
                        <p class="text-left" style="color: #495057; margin-top: 15px;">
                            <i class="fas fa-info-circle" style="color: #6c757d;"></i>
                            If your A record is not properly configured, the SSL certificate reconfiguration will fail.
                        </p>
                    </div>
                </div>
                
                <template slot="footer">
                    <div class="container text-center pb-4">
                        <base-button type="secondary" @click="modals.domainRetryConfirmation = false" class="mr-2">
                            Cancel
                        </base-button>
                        <base-button type="primary" @click="confirmDomainRetry">
                            Confirm & Reconfigure SSL
                        </base-button>
                    </div>
                </template>
            </modal>
        <!-- MODAL DOMAIN SSL RETRY CONFIRMATION-->

        <!-- MODAL FOR EMAIL TEMPLATES-->
            <modal :show.sync="modals.emailtemplate" headerClasses="justify-content-center">
                <h4 slot="header" class="title title-up" v-html="emailtemplate.title">Welcome Client Email Template</h4>
                <div class="row">
                    <div class="col-sm-4 col-md-4 col-lg-4 text-left">
                        <base-input
                                label="From Address:"
                                placeholder="ex. noreply@eyourdomain.com"
                                value=""
                                v-model="emailtemplate.fromAddress"
                                id="fromAddress"
                                @click="activeElement = 'fromAddress'"
                            >
                            </base-input>
                    </div>
                    <div class="col-sm-4 col-md-4 col-lg-4 text-left">
                        <base-input
                                label="From Name:"
                                placeholder="ex. Reset Password"
                                value=""
                                v-model="emailtemplate.fromName"
                                id="fromName"
                                @click="activeElement = 'fromName'"
                            >
                            </base-input>
                    </div>
                    <div class="col-sm-4 col-md-4 col-lg-4 text-left">
                        <base-input
                                label="Reply To:"
                                placeholder="ex. support@yourdomain.com"
                                value=""
                                v-model="emailtemplate.fromReplyto"
                                id="fromReplyto"
                                @click="activeElement = 'fromReplyto'"
                            >
                            </base-input>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12 col-md-12 col-lg-12 text-left">
                        <base-input
                                label="Email Subject:"
                                placeholder="ex. Welcome Account Setup"
                                value=""
                                v-model="emailtemplate.subject"
                                id="emailsubject"
                                @click="activeElement = 'emailsubject'"
                            >
                            </base-input>
                    </div>
                </div>
                <div class="row pt-2">
                    <div class="col-sm-12 col-md-12 col-lg-12 text-left">
                        <label>Email Content:</label>
                        <base-input>
                            <textarea
                            class="form-control"
                            id="emailcontent"
                            @click="activeElement = 'emailcontent'"
                            placeholder="Describe your target customer here" rows="50" style="min-height:180px" v-model="emailtemplate.content">
                            </textarea>
                        </base-input>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-12 text-left">
                        Short Code:
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-12 text-left">
                        <a href="#" @click="insertShortCode('[client-name]');">[client-name]</a> <a href="#" @click="insertShortCode('[client-firstname]');">[client-firstname]</a> <a href="#" @click="insertShortCode('[client-email]');">[client-email]</a> <a href="#" @click="insertShortCode('[client-new-password]');">[client-new-password]</a>&nbsp;
                        <a href="#" @click="insertShortCode('[client-company-name]');">[client-company-name]</a><br/>
                        <a href="#" @click="insertShortCode('[company-name]');">[company-name]</a> <span v-if="emailupdatemodule == 'em_prepaidtopuptwodaylimitclient'"><a href="#" @click="insertShortCode('[company-personal-name]');">[company-personal-name]</a></span> <a href="#" @click="insertShortCode('[company-domain]');">[company-domain]</a> <a href="#" @click="insertShortCode('[company-subdomain]');">[company-subdomain]</a> <a href="#" @click="insertShortCode('[company-email]');">[company-email]</a><br/>
                        <a href="#" @click="insertShortCode('[campaign-module-name]');">[campaign-module-name]</a> <a href="#" @click="insertShortCode('[campaign-name]');">[campaign-name]</a> <a href="#" @click="insertShortCode('[campaign-id]');">[campaign-id]</a> <a href="#" @click="insertShortCode('[campaign-spreadsheet-url]');">[campaign-spreadsheet-url]</a>&nbsp;
                    </div>
                </div>
                <template slot="footer">
                    <div class="container text-center pb-4">
                    <base-button :disabled="isSendingTestEmail" class="btn-danger m-2" @click="test_email_content();"> <i class="fas fa-eye"></i> {{ btnTestEmail }}</base-button>
                    <base-button @click="save_email_content();">Update Email Template</base-button>
                    </div>
                </template>
            </modal>
        <!-- MODAL FOR EMAIL TEMPLATES-->

         <!-- MODAL CONFIRMATION RESET CONNECTION-->
            <modal :show.sync="modals.resetstripeconnection" headerClasses="justify-content-center">
                <h4 slot="header" class="title title-up">Reset Your Payment Connection</h4>
                <div class="row">
                    <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                       <p>Resetting your stripe account will make not active all campaign until your Stripe connection is re-established.<br/>Do you wish to continue?</p>
                       <p>Please type "RESET" to confirm you want to do this.</p>
                       <div class="row">
                       <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>
                       <div class="col-sm-4 col-md-4 col-lg-4 text-center">
                            <base-input
                                placeholder="Type RESET"
                                v-model="confirmreset"
                                style="width:120px;margin:0 auto"
                                id="ConfirmResetConnection"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>
                       </div>
                       <small v-if="confirmreseterror">* Type "RESET" to confirm resetting the connection. (case sensitive)</small>
                    </div>
                </div>
                
                <template slot="footer">
                    <div class="container text-center pb-4">
                    <base-button id="btnconfirmreset" @click="process_resetconnection();">Confirm</base-button>
                    </div>
                </template>
            </modal>
        <!-- MODAL CONFIRMATION RESET CONNECTION-->

        <!-- MODAL CONFIRMATION CANCEL SUBSCRIPTION-->
            <modal :show.sync="modals.cancelsubscription" headerClasses="justify-content-center">
                <h4 slot="header" class="title title-up">Cancel subscription</h4>
                <div class="row">
                    <div class="col-sm-12 col-md-12 col-lg-12 text-center">
                       <p>Canceling your subscription will result in the removal of your campaigns, settings, and account from our system.<br/>Are you sure you want to proceed?</p>
                       <p>Please type "CANCEL" to confirm you want to do this.</p>
                       <div class="row">
                       <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>
                       <div class="col-sm-4 col-md-4 col-lg-4 text-center">
                            <base-input
                                placeholder="Type CANCEL"
                                v-model="confirmcancel"
                                style="width:130px;margin:0 auto"
                                id="ConfirmCancel"
                            >
                            </base-input>
                        </div>
                        <div class="col-sm-4 col-md-4 col-lg-4">&nbsp;</div>
                       </div>
                       <small v-if="confirmcancelerror">* Type "Cancel" to confirm cancelling subscription. (case sensitive)</small>
                    </div>
                </div>
                
                <template slot="footer">
                    <div class="container text-center pb-4">
                    <base-button id="btnconfirmcancel" @click="process_cancelsubscription();">Confirm</base-button>
                    </div>
                </template>
            </modal>
        <!-- MODAL CONFIRMATION CANCEL SUBSCRIPTION-->
        
        <!-- MODAL HELP -->
        <modal :show.sync="modals.help" headerClasses="">
            <div slot="header" class="d-flex flex-column gap-4">
                <h4 class="title title-up">{{activeHelpItem.title}}</h4>
            </div>
            <div v-if="modals.help" v-html="activeHelpItem.embedVideoCode"></div>
            <p class="text-dark mt-4" style="font-size: 14px;" v-html="activeHelpItem.desc"></p>
        </modal>
        <!-- MODAL HELP -->

        <!-- MODAL IS APP PASSWORD -->
        <modal :show.sync="modals.isAppPassword" headerClasses="justify-content-center">
                <h4 slot="header" class="title title-up">How to Generate an App Password</h4>
                <!-- <p style="margin-bottom: 16px; text-align: center; opacity: 0.8;">You can either watch the video tutorial below or follow the step-by-step instructions provided.</p> -->
                <div class="row">
                    <!-- <div class="col-12" style="margin-bottom: 16px;">
                        <iframe src="https://drive.google.com/file/d/1h8z4mc8tfQjkrJrwqWX391gSMLs2vMPy/preview" width="100%" height="480" allow="autoplay"></iframe>
                    </div> -->

                    <!-- FOR GOOGLE/GMAIL -->
                    <div class="col-12" v-if="customsmtp.host && customsmtp.host.trim() == 'smtp.gmail.com'">
                        <div class="row">
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px; font-size: 20px;">SMTP Setup Guide</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px;">1. Log in to Your Google Account</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Open your browser and log in to your Google account at <a href="https://myaccount.google.com/" target="_blank" rel="noopener noreferrer" style="color: #4286f4 !important;">Google Account</a>.</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px;">2. Enable 2-Step Verification</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Go to Security in your Google account settings.</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Find 2-Step Verification and enable it if it's not already activated.</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px;">3. Generate an App Password</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Scroll down to App Passwords in the Security section. (If there is no app password option, you can use the following link: <a href="https://myaccount.google.com/u/1/apppasswords" target="_blank" rel="noopener noreferrer" style="color: #4286f4 !important; word-wrap: break-word; ">https://myaccount.google.com/u/1/apppasswords</a>)</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Input the name of the application you want to generate a password. for example <span style="background: #ececec; padding: .15rem .3rem; border-radius: 4px;">SMTP-EMAIL</span></p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Click Generate, and a password like <span style="background: #ececec; padding: .15rem .3rem; border-radius: 4px;">abcd efgh ijkl mnop</span> will appear.</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px;">4. Use the App Password</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Copy the password and paste it into the App Password field in this form.</p>
                            </div>
                        </div>
                    </div>
                    <!-- FOR GOOGLE/GMAIL -->

                    <div class="col-12" v-if="!customsmtp.host || customsmtp.host.trim() != 'smtp.gmail.com'">
                        <div class="row">
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px; font-size: 20px;">SMTP Setup Guide</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px;">1. Log in to Your Application Account</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Log in to your application account (e.g., Gmail, Yahoo, Outlook, or another provider).</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px;">2. Enable 2-Step Verification (If Required)</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Enable 2FA (Two-Factor Authentication) in the security settings of your account, if required. Some providers, like Gmail, may require 2FA to generate an app password.</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px;">3. Generate an App Password (If Required)</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- If your provider requires it, go to Security Settings.</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Look for App Passwords (if available)</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Select the application type (e.g., SMTP-EMAIL) and click Generate.</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Copy the generated password.</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;"><strong>Note: </strong>Some providers may not require an app password. In this case, you can simply use your normal email password.</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px;">4. Use the App Password</p>
                                <p style="margin-left: 14px; margin-bottom: 0px;">- Paste the app password (or your regular password, if no app password is needed) into the relevant field in your application.</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 0px; font-size: 20px;">Important Note</p>
                            </div>
                            <div class="col-12" style="margin-bottom: 8px;">
                                <p style="margin-bottom: 0px;">For specific SMTP settings (such as SMTP host, port, and other configurations), please check the documentation or security settings of your email provider. Each provider (e.g., Gmail, Yahoo, Outlook, Zoho, etc.) may have different requirements for SMTP server configuration. Make sure to follow the instructions from your provider for the most accurate setup.</p>
                            </div>
                        </div>
                    </div>
                </div>
        </modal>
        <!-- MODAL IS APP PASSWORD -->

        <!-- MODAL CREATE SUB ACCOUNT LEAD CONNECTOR -->
        <modal :show.sync="modals.createSubAccountLeadConnector" class="modal-subleadconnector">
            <div slot="header" class="d-flex flex-column gap-4">
                <h4 class="title title-up">Select Sub Account</h4>
            </div>

            <div style="margin-top: -8px;">
                <p style="font-size: 13px;">Select the sub-accounts from your Lead Connector account to add as clients and create your custom menu. A maximum of 5 sub-accounts can be selected.</p>
                <el-input class="search-subleadconnector" v-model="searchSubAccountLeadConnector" type="text" clearable prefix-icon="el-icon-search" placeholder="Search Sub Account..."></el-input>
            </div>

            <div v-if="subAccountLeadConnectorSelected.length > 0" style="display: flex; justify-content: start; align-items: center; flex-wrap: wrap; margin-top: 20px; gap: 8px;">
                <div v-for="(value, index) in subAccountLeadConnectorSelected" :key="index" class="campaign-card-status-badge" style="background:#409EFF; color:white; margin-bottom:5px;"><span>{{ value.company }}</span></div>
            </div>

            <div 
                style="max-height: 700px; overflow-y: auto;"
                :style="{ marginTop: subAccountLeadConnectorSelected.length > 0 ? '18px' : '28px' }">
                <card 
                    v-for="(value , index) in filteredSubAccounts" 
                    :key="index" 
                    class="card-list-subaccount-leadconnector" 
                    :class="{
                        'card-list-subaccount-leadconnector-active': subAccountLeadConnectorSelected.some(item => item.id == value.id), 
                        'disabled': checkActionSubAccountLeadConnector('disabled', value),
                        'card-list-subaccount-leadconnector-cursor-default': checkActionSubAccountLeadConnector('cursorDefault', value)
                    }">
                    <div class="child-card-list-subaccount-leadconnector" @click="toggleSubAccountLeadConnector(value)">
                        <div class="d-flex justify-content-start align-items-center" style="gap: 16px;">
                            <p style="color: black !important; font-weight: bold; font-size: large;"><i class="fas fa-building mr-2"></i> {{ value.company }}</p>
                            <div v-if="checkActionSubAccountLeadConnector('badge', value)" class="campaign-card-status-badge" style="background:rgb(88, 92, 102); color:white; margin-bottom:5px;">
                                {{ checkActionSubAccountLeadConnector('message', value) }}
                            </div>
                        </div>
                        <div>
                            <p><i class="fas fa-envelope mr-2"></i> {{ value.email }}</p>
                        </div>
                    </div>
                </card>
            </div>
            
            <div class="d-flex justify-content-end">
                <base-button :disabled="isGhlV2CreateClientFromSubAccount || subAccountLeadConnectorSelected.length == 0" @click="createClientFromSubAccountLeadConnector">
                    Save <i v-if="isGhlV2CreateClientFromSubAccount" class="fas fa-spinner fa-spin ml-2" style="font-size: 16px;"></i>
                </base-button>
            </div>
        </modal>
        <!-- MODAL CREATE SUB ACCOUNT LEAD CONNECTOR -->

        <div id="popProcessing" class="popProcessing" style="display:none" v-html="popProcessingTxt"></div>
    </div>
</template>
<script>
import BaseInput from '../../../../components/Inputs/BaseInput.vue';
import BasePagination from '../../../../components/BasePagination.vue';
import { Select, Option, ColorPicker, Timeline, TimelineItem, MessageBox, Input , Button , Card , Step , Steps, Divider } from 'element-ui';
import { Modal,BaseRadio } from '/src/components';
import { formatCurrencyUSD } from '@/util/formatCurrencyUSD'
import Resumable from 'resumablejs'
import swal from 'sweetalert2';

export default {
  components: { 
    BaseInput,
    Modal, 
    BaseRadio,
    BasePagination,
    [Option.name]: Option,
    [Select.name]: Select,
    [ColorPicker.name]: ColorPicker,
    [Timeline.name]: Timeline,
    [TimelineItem.name]: TimelineItem,
    [Input.name] : Input,
    [Card.name] : Card,
    [Button.name] : Button ,
    [Step.name] : Step,
    [Steps.name] : Steps,
    [Divider.name] : Divider,
},
    data() {
      return {
        showPassword : false,
        charges_enabled: false,
        payouts_enabled: false,

        popProcessingTxt: 'Please wait, cancelling subscription ....',
        
        selectsPlaceEmbeddedCode: {
            PlaceEmbededCodeList: [
                { value: 'head', label: 'head tag'},
                { value: 'body', label: 'body tag'},
                { value: 'footer', label: 'footer tag'},
            ],
        },

        agencyEmbeddedCode: {
            embeddedcode: '',
            placeEmbedded: 'head',
        },

        embeddedCodeTitle: 'Agency',
        btnTestSMTP: 'Send test email',
        btnTestEmail: 'Send test email',
        isSendingTestSMTP: false,
        isSendingTestEmail: false,
        enabledDeletedAccountClient : false ,
        activePriceSettingTab:1,
        ru: false,
        ru1: false,
        ru2: false,
        ruLogoLoginAndRegister: false,
        ruLogoSidebar: false,
        apiurl: process.env.VUE_APP_DATASERVER_URL + '/api',
        images: {
            login: '/img/EMMLogin.png',
            register: '/img/EMMLogin.png',
            agency: 'https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/agencyregister.png',
        },
        logo: {
            loginAndRegister: '/img/logoplaceholder.png',
            sidebar: this.$global.globalCompanyPhoto || '/img/logoplaceholder.png',
        },
        modals: {
            emailtemplate: false,
            whitelabelingguide:false,
            domainRetryConfirmation: false,
            resetstripeconnection:false,
            cancelsubscription:false,
            help:false,
            isAppPassword: false,
            mindSpendConfig: false,
            mindSpendConfigEdit: false ,
            createSubAccountLeadConnector: false
        },
        emailtemplate: {
            title:'',
            subject: '',
            content: '',
            fromAddress: 'noreply@yourdomain.com',
            fromName: 'Reset Password',
            fromReplyto: 'support@yourdomain.com',
        },
        selectedDefault : null ,
        helpContentMap:[
            {   
                title:'Google Sheets Connect',
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1HL1QkAkb1Edvk-xJZ4v9zqFg1S7OpRAT/preview" width="100%" height="480" allow="autoplay"></iframe>',
                desc:'Google sheets is one of our defaults core integrations. By connecting your Google sheets account to our API, we are then able to create a fresh Google sheet for every single campaign that is created, then share that sheet to your customers as viewers, and also share it to your administrators as editors. After clicking the connect button you need to choose the Google account that you want to use, and make sure to select all checkbox permissions.<br>As your business grows you may see dozens or even hundreds of Google sheets in your account and you are free to organize these under any foldering structure you wish without breaking the API.<br>Connecting your Google sheets account is recommended but not required.<br>If you skip this step for now and create campaigns, those campaigns will simply have spreadsheets created for them. If you come back and connect your Google sheets at a later time those campaigns will continue to simply have spreadsheets and the new campaigns will have Google sheets.<br>If you need to change your Google sheet connection in the future, any campaign that was created under the old connection will no longer have a Google sheet update. Newly created campaigns will use the new Google sheet account.<br>All of our integrations are independent of each other and using or not using this integration does not affect your ability to use any of the other integrations or web hooks.'
            },
            {
                title:'Email SMTP',
                desc:"The system will send administrative emails to your clients and you have the ability to use your personal email address to send these notices. The default outbound email address is a non-branded address of “noreply at sitesettingsapi .com<br>Here you will 1. configure your settings to match what is required by your email vendor 2. then switch the checkbox to use personal SMTP, and 3. don't forget to save.<br>You should also send a test email and verify that you get a green success record before moving on.<br>Keep in mind that if you are using Gmail hosted personal or professional email, you must enable 2 step verification, and then use an App Password in this area.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1h8z4mc8tfQjkrJrwqWX391gSMLs2vMPy/preview" width="100%" height="480" allow="autoplay"></iframe>'
            },
            {
                title:'Whitelabel Your Domain',
                desc:"When you white label your domain you will remove your temporary dashboard subdomain and have a fully branded URL. It is important that you follow all three steps in order.<br>First, point your A record to our server's IP address. Directions for this are at this Link. You may use a full domain, however most agencies use a subdomain such as app.yourdomain leads.yourdomain or dashboard.yourdomain<br>Second, use some sort of tool such as the one here at the link and verify that your A record is fully pointed with only one IP address. It cannot have multiple IP addresses in the record. (Also if you are using a traffic protection tool such as Cloudflare, you must remove these for this subdomain) You do not have to create a subdomain on your side or an SSL certificate. We will do all of that on our side.<br>Finally, put your full white labeled domain in the box and press the save button.<br>We will then issue the SSL certificate and you will receive an email in about 10 minutes when everything is ready. (If you do not get a confirmation email within 30 minutes, or you see a warning message here, reach out for support)<br>Once your white label domain is enabled, you will want to make sure to log in exclusively to that URL and never log into the temporary subdomain again.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1GH07D97L_yTkArcBYUPhzWIJlMF0oT-s/preview" width="100%" height="480" allow="autoplay"></iframe>'
            },
            {
                title:'Stripe connect',
                desc:"The first step of automation and white labeling is to connect your stripe account. By connecting your stripe account to the platform, our API can bill your clients on your behalf and your clients will never see our branding.<br>When you mark up the lead, your stripe account will charge your clients the retail rate. When the transaction occurs, our API will instantly transfer the wholesale cost per lead into our account leaving the profits in your account.<br>Sometimes stripe will allow you to connect an existing account, and sometimes stripe will require you to create a new sub account.<br>After you have successfully connected your account you will see two green check marks. If you have created a brand new account or a brand new sub account during this process it often takes 60 seconds or more for the check marks to appear. Please simply refresh this screen after a couple of minutes.<br>It is also very important that you watch for any notifications from Stripe about your account. They often require additional documentation days or weeks later and if this documentation is not provided in a timely manner they will pause or even close your account.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1iMvKQuneF_h1kG0BSve3eGMF5adegojW/preview" width="100%" height="480" allow="autoplay"></iframe>',
            },
            {
                title:'Default Costs',
                desc:"<br>Here is where you are able to see your wholesale cost per lead, as well as control your default retail pricing.<br>Your wholesale cost is listed clearly here above the control box. Remember that for Enhanced Search 2.0 leads, the wholesale pricing enables up to 100% markup, and scales upward from the base cost. For example, if this agency set their retail cost at 1 dollar, then the wholesale cost would be 50 cents. If they set their retail cost at 75 cents, then the wholesale cost would be 45 cents. The site leads wholesale costs do not scale.<br>All pricing is per lead and each campaign has a confirmation screen that reviews the pricing prior to activation. You can override the default pricing on a campaign basis and also override this default pricing on a per client basis, we will go over this later in the client management and campaign management sections.<br>The first box on the left is where you can set up a one-time startup fee. If part of your business model is helping your clients install the pixel on their website and configure their integrations and CRM then you can charge for this right in the dashboard. This can also be controlled on a per-module basis<br>The next section is where you can set a monthly campaign fee. Some agencies call this a license fee or an access fee. If your clients have very low lead volume this can be set to make sure that your agency has a minimum monthly profit per campaign<br>The cost per lead is where you will set your default retail cost per lead. This amount is not in excess of your wholesale but is the final price per lead that your clients will see.<br>For example, in this example agency, their cost per lead for site leads is 25 cents per lead and they are charging 50 cents per lead to their clients. The end price to their clients is 50 cents per lead and the agency's profit is 25 cents per lead.<br>The wholesale cost is taken out of each transaction automatically through the Stripe API so there is no need for any billing or accounts receivable outside of the dashboard. Keep in mind that with the startup fee or monthly campaign fee, there is no split with the dashboard, that is all your profit.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1pFqwpowcjWDE6-0Sl6Ho2IKflb4e_sD8/preview" width="100%" height="480" allow="autoplay"></iframe>',

            },
            {
                title:'Customize Your Logo',
                desc:"In this section you will update both your internal and external logos as well as your banner images.<br>You have a login page and a registration page that can be customized with a logo and banner image. The logo on the left is an external logo. The logo on the right is for your internal left bar menu. Please follow the supported file types and sizes when uploading your images as well as your logos.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1C8vH5FCpKzYLARqlFGEhxN-H4GOlUBbx/preview" width="100%" height="480" allow="autoplay"></iframe>',

            },
            {
                title:'Product Names',
                desc:"In this section you can rename your product modules. Make sure that the module name and URL are unique and that there are no spaces in the module URL box. Make sure to hit the save button when you are done and the screen will refresh with your personalized product names in the key areas.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1ksAmmHRZvYy9JOJz8Za8S7_WxT5TMwdl/preview" width="100%" height="480" allow="autoplay"></iframe>',

            },
            {
                title:'Default Products',
                desc:"In this section you can control What products are available for clients who are registered through your public registration link. At any time you can also go to the client management tab and control their product access on a per client basis there also. <br>Some agencies will have only one product available by default that corresponds to their key use case, and then activate the other product by request or as part of an upsell<br>Most agencies leave all products available.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1Uc1B9AHbqSDDa-1hbsUuLJ_J7KzcRcaZ/preview" width="100%" height="480" allow="autoplay"></iframe>',

            },
            {
                title:'Email Templates',
                desc:"Here you can modify and personalize the email templates for the system emails that will be sent to your clients. This is a basic HTML editor and we recommend only using the short codes and plain text to ensure deliverability.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1Fj-xZIxjh1TRjZEinJ3Bg7h7myvcGUcE/preview" width="100%" height="480" allow="autoplay"></iframe>',

            },
            {
                title:'Support Widget',
                desc:"Here you can embed a support widget of your choice that will display when your clients are logged in. Some agencies will use a support widget that is integrated into their personal CRM so they can manage help tickets and such. Some agencies will simply integrate a chat widget or an AI chat widget.",
                embedVideoCode:'<iframe src="https://drive.google.com/file/d/1BYIRiy1YMFgcAu1s6vScoPXe5LL25BWC/preview" width="100%" height="480" allow="autoplay"></iframe>',
            },
            { 
                title: 'Site ID Basic Information',
                desc: `${this.$global.globalModulNameLink.local.name} campaigns can use either Basic information or Advanced information, similar to ${this.$global.globalModulNameLink.enhance.name} . You must set the retail price for each of these data options independently of each other.`,
                embedVideoCode: '<iframe src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/basicdata.jpeg" width="100%" height=180></iframe>'
            },
            {
                title: 'Site ID Advanced Information',
                desc: `${this.$global.globalModulNameLink.local.name} campaigns can use either Basic information or Advanced information, similar to ${this.$global.globalModulNameLink.enhance.name} . You must set the retail price for each of these data options independently of each other.`,
                embedVideoCode: '<iframe src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/advancedata.jpeg" width="100%" height=230></iframe>'
            },
            {
                title: 'Clean ID Advanced Information',
                desc: "Clean ID campaigns can use either Basic information or Advanced information, similar to Enhance ID. You must set the retail price for each of these data options independently of each other.",
                embedVideoCode: '<iframe src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/advancedata.jpeg" width="100%" height=230></iframe>'
            }
        ],
        subAccountLeadConnector: [
            {
                id: 1,
                email: 'Johndoe@gmail.com',
                company: 'Digital Marketing Pro', 
                phone: '+123456789',
                country: 'US',
                location_id: '121212122',
                company_id: '1212121212',
                isExistsAnotherPlatform: false,
                isExistsClient: false,
            }
        ],
        subAccountLeadConnectorSelected: [],
        maxSelectedSubAccount: 5,
        activeHelpItem:  {   
            title:'Google Sheets Connect',
            embedVideoCode:'<iframe src="https://drive.google.com/file/d/1HL1QkAkb1Edvk-xJZ4v9zqFg1S7OpRAT/preview" width="100%" height="480" allow="autoplay"></iframe>',
            desc:'Google sheets is one of our defaults core integrations. By connecting your Google sheets account to our API, we are then able to create a fresh Google sheet for every single campaign that is created, then share that sheet to your customers as viewers, and also share it to your administrators as editors. After clicking the connect button you need to choose the Google account that you want to use, and make sure to select all checkbox permissions.<br>As your business grows you may see dozens or even hundreds of Google sheets in your account and you are free to organize these under any foldering structure you wish without breaking the API.<br>Connecting your Google sheets account is recommended but not required.<br>If you skip this step for now and create campaigns, those campaigns will simply have spreadsheets created for them. If you come back and connect your Google sheets at a later time those campaigns will continue to simply have spreadsheets and the new campaigns will have Google sheets.<br>If you need to change your Google sheet connection in the future, any campaign that was created under the old connection will no longer have a Google sheet update. Newly created campaigns will use the new Google sheet account.<br>All of our integrations are independent of each other and using or not using this integration does not affect your ability to use any of the other integrations or web hooks.'
        },
        activeElement: '',
        emailupdatemodule: '',

        userData: '',
        sidebarcolor:'#942434',
        backgroundtemplatecolor: '#1e1e2f',
        boxcolor: '#ffffff',
        textcolor: '#FFFFFF',
        linkcolor: '#942434',
        fonttheme: 'Poppins',
        fontthemeactive: 'Poppins',

        GoogleConnectFalse: false,
        GoogleConnectTrue: false,

        leadsLocalName : this.$global.globalModulNameLink.local.name,
        leadsLocalUrl : this.$global.globalModulNameLink.local.url,
        leadsLocatorName: this.$global.globalModulNameLink.locator.name,
        leadsLocatorUrl: this.$global.globalModulNameLink.locator.url,
        leadsEnhanceName: this.$global.globalModulNameLink.enhance.name,
        leadsEnhanceUrl: this.$global.globalModulNameLink.enhance.url,
        leadsB2bName: this.$global.globalModulNameLink.b2b.name,
        leadsB2bUrl: this.$global.globalModulNameLink.b2b.url,
        leadsSimplifiName: this.$global.globalModulNameLink.simplifi.name,
        leadsSimplifiUrl: this.$global.globalModulNameLink.simplifi.url,

        customsmtp: {
            default: true,
            host: '',
            port: '',
            username: '',
            password: '',
            security: 'ssl',
        },
        prevcustomsmtp: {
            default: true,
            host: '',
            port: '',
            username: '',
            password: '',
            security: 'ssl',
        },

        txtStatusConnectedAccount: 'Setup your stripe account',
        txtStatusConnectedExistingAccount: 'Connect existing stripe account',
        ActionBtnConnectedAccount: '',
        DisabledBtnConnectedAccount: false,
        statusColorConnectedAccount: '',
        refreshURL: '/configuration/general-setting/',
        returnURL: '/configuration/general-setting/',

        accConID: '',

        DownlineDomain:'',
        DownlineSubDomain:'',
        DownlineDomainStatus:'',
        
        Whitelabellingstatus:false,
        agreewhitelabelling:true,
        chkagreewl:true,
        domainSetupCompleted: false,

        radios: {
            packageID: '',
            lastpackageID: '',
            freeplan: '',
            whitelabeling: {
                monthly: '',
                monthlyprice: '',
                yearly: '',
                yearlyprice: '',
                monthly_disabled: false,
                yearly_disabled:false,
            },
            nonwhitelabelling: {
                monthly: '',
                monthlyprice: '',
                yearly: '',
                yearlyprice: '',
                monthly_disabled: false,
                yearly_disabled:false,
            }
        },

        plannextbill:'free',

        /** FOR SET PRICE */
        CompanyActiveID: '',
        AgencyCompanyName: '',

        LeadspeekPlatformFee: '0',
        LeadspeekCostperlead: '0',
        LeadspeekCostperleadAdvanced: '0',
        LeadspeekMinCostMonth: '0',

        LocatorPlatformFee: '0',
        LocatorCostperlead: '0',
        LocatorMinCostMonth: '0',

        EnhancePlatformFee: '0',
        EnhanceCostperlead: '0',
        EnhanceMinCostMonth: '0',

        B2bPlatformFee: '0',
        B2bCostperlead: '0',
        B2bMinCostMonth: '0',


        CleanCostperlead: '0',
        CleanCostperleadAdvanced: '0',

        SimplifiMaxBid: '0',
        SimplifiDailyBudget: '0',
        SimplifiAgencyMarkup: '0',
        simplifiPriceRule: {
            maxBid: {
                default: 12,
                minimum: 8
            },
            dailyBudget: {
                minimum: 5
            },
        },
        simplifiErrorInput: {
            maxBid: "",
            dailyBudget: ""
        },
        agencyMarkup: {
            list : [
                {
                    value : 0,
                    text : '0%',
                },
                {
                    value : 5,
                    text : '5%',
                },
                {
                    value : 10,
                    text : '10%',
                },
                {
                    value : 15,
                    text : '15%',
                },
                {
                    value : 20,
                    text : '20%',
                },
                {
                    value : 25,
                    text : '25%',
                },
                {
                    value : 30,
                    text : '30%',
                },
                {
                    value : 40,
                    text : '40%',
                },
                {
                    value : 50,
                    text : '50%',
                },
            ]
        },

        CleanCostperlead: '0',
        CleanCostperleadAdvanced: '0',

        lead_FirstName_LastName : '0',
        lead_FirstName_LastName_MailingAddress: '0',
        lead_FirstName_LastName_MailingAddress_Phone: '0',

        defaultPaymentMethod: 'stripe',
        packageName: '',

        costagency : {
            local : {
                'Weekly' : {
                    LeadspeekPlatformFee: '0',
                    LeadspeekCostperlead: '0',
                    LeadspeekCostperleadAdvanced: '0',
                    LeadspeekMinCostMonth: '0',
                },
                'Monthly' : {
                    LeadspeekPlatformFee: '0',
                    LeadspeekCostperlead: '0',
                    LeadspeekCostperleadAdvanced: '0',
                    LeadspeekMinCostMonth: '0',
                },
                'OneTime' : {
                    LeadspeekPlatformFee: '0',
                    LeadspeekCostperlead: '0',
                    LeadspeekCostperleadAdvanced: '0',
                    LeadspeekMinCostMonth: '0',
                },
                'Prepaid' : {
                    LeadspeekPlatformFee: '0',
                    LeadspeekCostperlead: '0',
                    LeadspeekCostperleadAdvanced: '0',
                    LeadspeekMinCostMonth: '0',
                }
            },

            locator : {
                'Weekly' : {
                    LocatorPlatformFee: '0',
                    LocatorCostperlead: '0',
                    LocatorMinCostMonth: '0',
                },
                'Monthly' : {
                    LocatorPlatformFee: '0',
                    LocatorCostperlead: '0',
                    LocatorMinCostMonth: '0',
                },
                'OneTime' : {
                    LocatorPlatformFee: '0',
                    LocatorCostperlead: '0',
                    LocatorMinCostMonth: '0',
                },
                'Prepaid' : {
                    LocatorPlatformFee: '0',
                    LocatorCostperlead: '0',
                    LocatorMinCostMonth: '0',
                }
            },

            enhance : {
                'Weekly' : {
                    EnhancePlatformFee: '0',
                    EnhanceCostperlead: '0',
                    EnhanceMinCostMonth: '0',
                },
                'Monthly' : {
                    EnhancePlatformFee: '0',
                    EnhanceCostperlead: '0',
                    EnhanceMinCostMonth: '0',
                },
                'OneTime' : {
                    EnhancePlatformFee: '0',
                    EnhanceCostperlead: '0',
                    EnhanceMinCostMonth: '0',
                },
                'Prepaid' : {
                    EnhancePlatformFee: '0',
                    EnhanceCostperlead: '0',
                    EnhanceMinCostMonth: '0',
                }
            },

            b2b : {
                'Weekly' : {
                    B2bPlatformFee: '0',
                    B2bCostperlead: '0',
                    B2bMinCostMonth: '0',
                },
                'Monthly' : {
                    B2bPlatformFee: '0',
                    B2bCostperlead: '0',
                    B2bMinCostMonth: '0',
                },
                'OneTime' : {
                    B2bPlatformFee: '0',
                    B2bCostperlead: '0',
                    B2bMinCostMonth: '0',
                },
                'Prepaid' : {
                    B2bPlatformFee: '0',
                    B2bCostperlead: '0',
                    B2bMinCostMonth: '0',
                }
            },

            clean : {
                CleanCostperlead: "0.5",
                CleanCostperleadAdvanced: "1",
            },

            locatorlead: {
                FirstName_LastName: '0',
                FirstName_LastName_MailingAddress: '0',
                FirstName_LastName_MailingAddress_Phone: '0',
            }
        },

        txtLeadService: 'weekly',
        txtLeadIncluded: 'in that weekly charge',
        txtLeadOver: 'from the weekly charge',

        selectsPaymentTerm: {
            PaymentTermSelect: 'Weekly',
            PaymentTerm: [
                // { value: 'One Time', label: 'One Time billing'},
                // { value: 'Weekly', label: 'Weekly Billing'},
                // { value: 'Monthly', label: 'Monthly Billing'},
            ],
        },
        selectsAppModule: {
                AppModuleSelect: 'LeadsPeek',
                AppModule: [
                    { value: 'LeadsPeek', label: 'LeadsPeek' },
                ],
                LeadsLimitSelect: 'Day',
                LeadsLimit: [
                    { value: 'Day', label: 'Day'},
                ],
        },
        /** FOR SET PRICE */

        confirmreset: '',
        confirmreseterror: false,
        confirmcancel: '',
        confirmcancelerror: false,
        trialEndDate:'',
        notPassSixtyDays:false,

        txtPayoutsEnabled: false, 
        txtpaymentsEnabled: false, 
        txtErrorRequirements: '', 
        is_whitelabeling: null,

        rootSiteIDCostPerLead: 0,
        rootSiteIDCostPerLeadAdvanced: 0,
        rootSearchIDCostPerLead:0,
        rootEnhanceIDCostPerLead:0,
        rootB2bIDCostPerLead:0,

        m_LeadspeekPlatformFee: 0,
        m_LeadspeekMinCostMonth: 0,
        m_LeadspeekLocatorPlatformFee: 0,
        m_LeadspeekLocatorMinCostMonth: 0,
        m_LeadspeekEnhancePlatformFee: 0,
        m_LeadspeekEnhanceMinCostMonth: 0,
        m_LeadspeekB2BPlatformFee: 0,
        m_LeadspeekB2BMinCostMonth: 0,
        prevDefaultRetailPrice: null,
        rootCostAgency : {
            local : {
                'Weekly' : {
                    LeadspeekCostperlead: '0',
                },
                'Monthly' : {
                    LeadspeekCostperlead: '0',
                },
                'OneTime' : {
                    LeadspeekCostperlead: '0',
                },
                'Prepaid' : {
                    LeadspeekCostperlead: '0',
                }
            },

            locator : {
                'Weekly' : {
                    LocatorCostperlead: '0',
                },
                'Monthly' : {
                    LocatorCostperlead: '0',
                },
                'OneTime' : {
                    LocatorCostperlead: '0',
                },
                'Prepaid' : {
                    LocatorCostperlead: '0',
                }
            },

            enhance : {
                'Weekly' : {
                    EnhanceCostperlead: '0',
                },
                'Monthly' : {
                    EnhanceCostperlead: '0',
                },
                'OneTime' : {
                    EnhanceCostperlead: '0',
                },
                'Prepaid' : {
                    EnhanceCostperlead: '0',
                }
            },

            b2b : {
                'Weekly' : {
                    B2bCostperlead: '0',
                },
                'Monthly' : {
                    B2bCostperlead: '0',
                },
                'OneTime' : {
                    B2bCostperlead: '0',
                },
                'Prepaid' : {
                    B2bCostperlead: '0',
                }
            },

            clean : {
                CleanCostperlead: "0.5",
                CleanCostperleadAdvanced: "1",
            },
        },
        defaultModule: [
            {
                type: 'local',
                name: '',
                status: true,
                icon: 'far fa-eye'
            },
            {
                type: 'locator',
                name: '',
                status: true,
                icon: 'fas fa-map-marked'
            },
            {
                type: 'enhance',
                name: '',
                status: true,
                icon: 'fa-solid fa-angles-up'
            },
            {
                type: 'b2b',
                name: '',
                status: true,
                icon: 'fa-solid fa-building'
            },
            {
                type: 'simplifi',
                name: '',
                status: true,
                icon: 'fa-solid fa-building'
            },

        ],
        isLoadingDefaultRetailPrices : false , 
        isLoadingWhiteLabelingDomain : false ,
        isLoadingColorPalete : false , 
        isLoadingFont : false ,
        isLoadingCostumeModule : false ,
        isLoadingSaveDefaultModule: false,
        isLoadingEmailSettings : false , 
        isLoadingEmbeddedSupportWidget : false ,
        isLoadingDeleteClientStatus : false ,
        activeSection: '',
        colors: {
            sidebar: '',
            text: '',
        },
        ghlV2Connected: false,
        isConnectDisconnectGhlv2: false,
        searchSubAccountLeadConnector: '',
        isGhlV2GetListSubAccountsAll: false,
        isGhlV2CreateClientFromSubAccount: false,

        isLoadingAddEditMinimumSpend: false,
        isLoadingFetchMinimumSpend: true,

        // Flag to prevent duplicate SSL retry requests
        isRetryingDomainSSL: false,

        planName: '',
        months: [''],
        flatMonth: '',
        minimumspendError: {
            planName: false,
            monthlySpendList: [false],
            flatMonth: false,
        },

        idMinimumSpendEdit: '',
        planNameEdit: '',
        monthsEdit: [''],
        flatMonthEdit: '',
        minimumspendErrorEdit: {
            planName: false,
            monthlySpendList: [false],
            flatMonth: false,
        },

        monthMaxLengh: 12,
        minimumSpendLists: [
            // {
            //     id: 1755072497260,
            //     planName: "Plan Ke 1",
            //     months: [
            //         "50",
            //         "100",
            //         "150",
            //     ],
            //     flatMonth: "200",
            //     isDefault: true
            // },
            // {
            //     id: 1755072553556,
            //     planName: "Plan ke 2",
            //     months: [
            //         "100",
            //         "200",
            //         "300",
            //         "400",
            //     ],
            //     flatMonth: "500",
            //     isDefault: false
            // }
        ],
        paginationMinimumSpend: {
            perPage: 3,
            currentPage: 1,
            total: 0,
            from: 0,
            to: 0,
        },
      };
    },
    methods: {
        async handleFeedbackConnectGhlV2(event) {
            if(event.data.ghl_status === "connected"){
                if(!this.$global.systemUser && this.$global.idsys == this.$global.masteridsys){
                    this.checkGohighlevelConnect();
                }
            }
        },
        isInIframe() {
            try {
                return window.self !== window.top;
            } catch (e) {
                // Tidak boleh akses window.top → berarti sedang dalam iframe
                return true;
            }
        },
        checkActionSubAccountLeadConnector(type, value) {
            const handlers = {
                badge: () => {
                    return (value.isExistsAnotherPlatform || !value.email || value.isExistsClient)
                },
                disabled: () => {
                	return (value.isExistsAnotherPlatform || !value.email);
                },
                message: () => {
                	if (value.isExistsAnotherPlatform) return 'email is already in use by another platform.'
                    else if (!value.email) return 'email is empty. please update it in your lead connector account.'
                    else if (value.isExistsClient) return 'email has been created as your client'
                    else return ''
                },
                cursorDefault: () => {
                	return (value.isExistsAnotherPlatform || !value.email || value.isExistsClient)
                }
            }

            if(handlers[type]) {
                return handlers[type]();
            }

            return null;
        },
        toggleSubAccountLeadConnector(account) {
            const accountId = typeof(account.id) != 'undefined' ? account.id : null;
            const email = typeof(account.email) != 'undefined' ? account.email : null;
            const isExistsAnotherPlatform = typeof(account.isExistsAnotherPlatform) != 'undefined' ? account.isExistsAnotherPlatform : null;
            const isExistsClient = typeof(account.isExistsClient) != 'undefined' ? account.isExistsClient : null;

            // validation
            if (!email) {
                return this.$notify({type: 'danger', icon: 'tim-icons icon-bell-55', message: 'email is empty. please update it in your Lead Connector account.'});
            }
            if (isExistsAnotherPlatform) {
               return this.$notify({type: 'danger', icon: 'tim-icons icon-bell-55', message: 'email is already in use by another platform.'}); 
            }
            if (isExistsClient) {
                return;
            }
            if (!accountId) {
                return this.$notify({type: 'danger', icon: 'tim-icons icon-bell-55', message: 'account id empty'});
            }
            // validation

            // process toggle active
            const index = this.subAccountLeadConnector.findIndex(account => account.id === accountId);
            if (index !== -1) {
                const currentItem = this.subAccountLeadConnector[index];
                const currentID = currentItem.id;
                const subAccountExists = this.subAccountLeadConnectorSelected.some(item => item.id == currentID);
                
                if (!subAccountExists) { // jika selected
                    if (this.subAccountLeadConnectorSelected.length >= this.maxSelectedSubAccount) { // jika lebih dari selected account
                        return this.$notify({type: 'danger', icon: 'tim-icons icon-bell-55', message: 'You can select up to 5 sub accounts only'});
                    }
                    this.subAccountLeadConnectorSelected.push(currentItem);
                } else { // jika unselected
                    this.subAccountLeadConnectorSelected = this.subAccountLeadConnectorSelected.filter(item => item.id != currentID)
                }
            }
            // process toggle active
        },
        togglePassword() {
           this.showPassword = !this.showPassword
        },
        checkGohighlevelConnect() {
            this
            .$store
            .dispatch('checkGohighlevelConnect', {
                companyID: this.userData.company_id,
            })
            .then(response => {
                this.ghlV2Connected = response.ghlV2Connected;
                if(response.ghlv2FirstTimeConnect === true) {
                    this.scrollToSection('gohigh-level');
                    this.$notify({
                        type: "success",
                        message: "Successfully installed the app.",
                        icon: "fas fa-save"
                    });
                }
            })
            .catch(error => {
                this.ghlV2Connected = false;
                const message = typeof(error.response.data.message) != 'undefined' ? error.response.data.message  : 'Something Wrong';
                this.$notify({
                    type: 'danger',
                    icon: 'fas fa-bug',
                    message: message
                })
            });
        },
        connectDisconnectGhlv2() {
            if(!this.ghlV2Connected) { // connect
                this.generateGHLv2AuthUrl();
            } else { // disconnect
                this.disconnectGhlv2();
            }
        },
        generateGHLv2AuthUrl() {
            MessageBox.prompt('Enter the text that you want to display as your single sign on custom menu link. (30 Characters Max)', 'Enter Your Custom Menu Link Text.', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                inputPattern: /^$|^[a-zA-Z0-9\s]{1,30}$/,
                inputErrorMessage: 'Only letters, numbers, and spaces, max 30 characters',
                inputPlaceholder: '[Agency-Name]',
                inputValue: this.userData.company_name,
                customClass: 'message-general-ghl'
            })
            .then(({ value }) => {
                const isInIframe = this.isInIframe();
                this.isConnectDisconnectGhlv2 = true;
                this
                .$store
                .dispatch('generateGHLv2AuthUrl', {
                    company_id: this.userData.company_id,
                    subdomain_url: window.location.href,
                    custom_menu_name: value,
                    user_ip: this.userData.ip_login,
                    is_in_iframe: isInIframe
                })
                .then(response => {
                    const result = typeof(response.result) != 'undefined' ? response.result : '';
                    const url = typeof(response.url) != 'undefined' ? response.url : '';
                    // console.log({result, url});
                    
                    if(result == 'success' && url != ''){
                        if(isInIframe){
                            window.open(url, '_blank');
                            this.isConnectDisconnectGhlv2 = false;
                        }else{
                            window.location.href = url;
                        }
                    }else{
                        this.checkGohighlevelConnect();
                        this.isConnectDisconnectGhlv2 = false;
                        this.$notify({
                            type: 'danger',
                            icon: 'fas fa-bug',
                            message: 'Something Went Wrong',
                        });
                    }
                })
                .catch(error => {
                    console.error(error);
                    this.checkGohighlevelConnect();
                    this.isConnectDisconnectGhlv2 = false;

                    const message = (typeof(error.response.data.message) != 'undefined') ? error.response.data.message : 'Something Went Wrong';
                    this.$notify({
                        type: 'danger',
                        icon: 'fas fa-bug',
                        message: message,
                    });
                })
            })
            
        },
        disconnectGhlv2() {
            MessageBox.confirm('Are You Sure Disconnect Lead Connector', 'Warning', {
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel',
                type: 'warning'
            })
            .then(() => {
                this.isConnectDisconnectGhlv2 = true;
                this
                .$store
                .dispatch('disconnectGhlv2', {
                    company_id: this.userData.company_id,
                    user_ip: this.userData.ip_login
                })
                .then(response => {
                    this.isConnectDisconnectGhlv2 = false;
                    this.ghlV2Connected = false;
                    this.$notify({
                        type: "success",
                        message: "Successfully uninstalled the app.",
                        icon: "fas fa-save"
                    });
                })
                .catch(error => {
                    this.checkGohighlevelConnect();
                    this.isConnectDisconnectGhlv2 = false;

                    const message = typeof(error.response.data.message) != 'undefined' ? error.response.data.message : 'Something Went Wrong';
                    this.$notify({
                        type: "primary",
                        message: message,
                        icon: "fas fa-bug"
                    });
                });
            })
            .catch(() => {
                this.isConnectDisconnectGhlv2 = false;
                return;
            });
        },
        changePageMinimumSpend() {
            this.getMinimumSpendList();
        },
        keyupInputMinimumSpend(form = '', variable = '', index = 0) {
            // console.log('keyupInputMinimumSpend', {index});
            if (form == 'add') {
                switch (variable) {
                    case 'plan_name' : 
                        this.minimumspendError.planName = (this.planName == ''); break;
                    case 'months' :
                        this.$set(this.minimumspendError.monthlySpendList, index, (this.months[index] == ''));
                        break;
                    case 'flat_month' :
                        this.minimumspendError.flatMonth = (this.flatMonth == ''); break;
                }
            } else if (form == 'edit') {
                switch (variable) {
                    case 'plan_name' : 
                        this.minimumspendErrorEdit.planName = (this.planNameEdit == ''); break;
                    case 'months' :
                        this.$set(this.minimumspendErrorEdit.monthlySpendList, index, (this.monthsEdit[index] == ''));
                        break;
                    case 'flat_month' :
                        this.minimumspendErrorEdit.flatMonth = (this.flatMonthEdit == ''); break;
                }
            }
        },
        getMinimumSpendList() {
            this.isLoadingFetchMinimumSpend = true;
            this
            .$store
            .dispatch('getMinimumSpendList', {
                page: this.paginationMinimumSpend.currentPage,
            })
            .then(response => {
                this.isLoadingFetchMinimumSpend = false;

                this.paginationMinimumSpend.currentPage = response.minimumSpendLists.current_page;
                this.paginationMinimumSpend.total = response.minimumSpendLists.total;
                this.paginationMinimumSpend.lastPage = response.minimumSpendLists.last_page;
                this.paginationMinimumSpend.from = response.minimumSpendLists.from ? response.minimumSpendLists.from : 0;
                this.paginationMinimumSpend.to = response.minimumSpendLists.to ? response.minimumSpendLists.to : 1;
                
                this.minimumSpendLists = response.minimumSpendLists.data;
            })
            .catch(error => {
                this.minimumSpendLists = [];
                this.isLoadingFetchMinimumSpend = false;
                this.$notify({ type: 'danger', message: 'Something Wrong Get Minimum Spend List', icon: 'fas fa-bug' }); 
            });
        },
        processAddEditMinimumSpendList(id) {
            if (id == '') { // add 
                let isErrorValidation = false;

                // validation plan name
                if(this.planName == '') {
                    this.minimumspendError.planName = true;
                    isErrorValidation = true;
                }
                // validation plan name

                // validation month list
                this.months.forEach((item, index) => {
                	if(item == '') {
                        this.$set(this.minimumspendError.monthlySpendList, index, true);
                        isErrorValidation = true;
                    }
                })
                // validation month list

                // validation flat month
                if(this.flatMonth == '') {
                    this.minimumspendError.flatMonth = true;
                    isErrorValidation = true;
                }
                // validation flat month

                if(isErrorValidation === true) {
                    this.$notify({ type: 'danger', message: 'Please check the required fields.', icon: 'fas fa-bug' }); 
                    return;
                }

                this.isLoadingAddEditMinimumSpend = true;
                this
                .$store
                .dispatch('createMinimumSpendConfig', {
                    planName: this.planName,
                    months: this.months,
                    flatMonth: this.flatMonth,
                    ipLogin: this.$store.getters.userData.ip_login
                })
                .then(response => {
                    // console.log(response);
                    this.isLoadingAddEditMinimumSpend = false;
                    this.closeHelpModalMinSpendModal();
                    this.getMinimumSpendList();
                    this.$notify({ type: 'success', message: 'Add Minimum Spend Configuration Successfully', icon: 'fas fa-bug' }); 
                })
                .catch(error => {
                    console.error(error);
                    this.isLoadingAddEditMinimumSpend = false;
                    const message = typeof(error.response.data.message) != 'undefined' ? error.response.data.message : 'Something Went Wrong.';
                    this.$notify({ type: 'danger', message: message, icon: 'fas fa-bug' }); 
                })
            } else { // update
                if(id == 'system') {
                    this.$notify({ type: 'danger', message: 'Plan Base System Cannot Edit', icon: 'fas fa-bug' });
                    return;
                }

                let isErrorValidation = false;

                // validation plan name
                if(this.planNameEdit == '') {
                    this.minimumspendErrorEdit.planName = true;
                    isErrorValidation = true;
                }
                // validation plan name

                // validation month list
                this.monthsEdit.forEach((item, index) => {
                	if(item == '') {
                        this.$set(this.minimumspendErrorEdit.monthlySpendList, index, true);
                        isErrorValidation = true;
                    }
                })
                // validation month list

                // validation flat month
                if(this.flatMonthEdit == '') {
                    this.minimumspendError.flatMonthEdit = true;
                    isErrorValidation = true;
                }
                // validation flat month

                if(isErrorValidation === true) {
                    this.$notify({ type: 'danger', message: 'Please check the required fields.', icon: 'fas fa-bug' }); 
                    return;
                }

                this.isLoadingAddEditMinimumSpend = true;
                this
                .$store
                .dispatch('updateMinimumSpendConfig', {
                    idMinimumSpend: this.idMinimumSpendEdit,
                    planName: this.planNameEdit,
                    months: this.monthsEdit,
                    flatMonth: this.flatMonthEdit,
                    ipLogin: this.$store.getters.userData.ip_login
                })
                .then(response => {
                    // console.log(response);
                    this.isLoadingAddEditMinimumSpend = false;
                    this.closeHelpModalEditMinSpendModal();
                    this.getMinimumSpendList();
                    this.$notify({ type: 'success', message: 'Add Minimum Spend Configuration Successfully', icon: 'fas fa-bug' }); 
                })
                .catch(error => {
                    console.error(error);
                    this.isLoadingAddEditMinimumSpend = false;
                    const message = typeof(error.response.data.message) != 'undefined' ? error.response.data.message : 'Something Went Wrong.';
                    this.$notify({ type: 'danger', message: message, icon: 'fas fa-bug' }); 
                })
            }
        },
        processDeleteMinimumSpendList(id) {
            swal.fire({
                title: 'Are you sure want to delete this?',
                text: "You won't be able to revert this!",
                icon: '',
                showCancelButton: true,
                customClass: {
                    confirmButton: 'btn btn-fill mr-3',
                    cancelButton: 'btn btn-danger btn-fill'
                },
                confirmButtonText: 'Yes, delete it!',
                buttonsStyling: false
            }).then(result => {
                if (!result.isConfirmed) {
                    return;
                }

                this
                .$store
                .dispatch('deleteMinimumSpendConfig', {
                    idMinimumSpend: id,
                    ipLogin: this.$store.getters.userData.ip_login
                })
                .then(response => {
                    // console.log(response);
                    this.getMinimumSpendList();
                    this.$notify({ type: 'success', message: 'Delete Minimum Spend Configuration Successfully', icon: 'fas fa-bug' }); 
                })
                .catch(error => {
                    console.error(error);
                    const message = typeof(error.response.data.message) != 'undefined' ? error.response.data.message : 'Something Wrong Delete Minimum Spend List';
                    this.$notify({ type: 'danger', message: message, icon: 'fas fa-bug' }); 
                })
            });
        },
        closeHelpModalMinSpendModal() {
            this.planName = '';
            this.months = [''];
            this.flatMonth = '';
            this.minimumspendError.planName = false;
            this.minimumspendError.monthlySpendList = [false];
            this.minimumspendError.flatMonth = false;
            this.modals.mindSpendConfig = false
        },
        closeHelpModalEditMinSpendModal() {
            this.planNameEdit = '';
            this.monthsEdit = [''];
            this.flatMonthEdit = '';
            this.minimumspendErrorEdit.planName = false;
            this.minimumspendErrorEdit.monthlySpendList = [false];
            this.minimumspendErrorEdit.flatMonth = false;
            this.modals.mindSpendConfigEdit = false
        },
        openHelpModalMinSpendModal() {
            this.modals.mindSpendConfig = true
        },
        openHelpModalEditMinSpendModal(item) {
            if(item.id == 'system') {
                this.$notify({ type: 'danger', message: 'Plan Base System Cannot Edit' });
                return;
            }
            
            this.idMinimumSpendEdit = item.id;
            this.planNameEdit = item.planName;
            this.monthsEdit = [...item.months];
            this.flatMonthEdit = item.flatMonth;
            this.modals.mindSpendConfigEdit = true;
        },
        openHelpModalCreateSubAccountLeadConnector() {
            // development
            // this.subAccountLeadConnector = [];
            // for(let i = 0; i < 5; i++) {
            //     this.subAccountLeadConnector.push({
            //         id: i + 1,
            //         email: i == 2 ? '' : `email${i + 1}@gmail.com`,
            //         company: `company ${i + 1}`,
            //         phone: '12345678',
            //         country: 'US',
            //         location_id: 'location_id',
            //         company_id: 'company_id',
            //         isExistsAnotherPlatform: i == 3 ? true : false,
            //         isExistsClient: i == 4 ? true : false,
            //     });
            // }
            // this.modals.createSubAccountLeadConnector = true;
            // development

            if(this.isGhlV2GetListSubAccountsAll) {
                return;
            }

            this.isGhlV2GetListSubAccountsAll = true;
            this.$store.dispatch('ghlv2GetListSubAccountsAll', {
                company_id: this.$store.getters.userData.company_id
            })
            .then(response => {
                this.isGhlV2GetListSubAccountsAll = false;
                this.subAccountLeadConnector = response.listSubAccounts;
                this.modals.createSubAccountLeadConnector = true;
            }).catch(error => {
                this.isGhlV2GetListSubAccountsAll = false;
                const message = error.response && error.response.data && error.response.data.message ? error.response.data.message : 'Something Wrong Get List Sub Accounts';
                this.$notify({ type: 'danger', message: message, icon: 'fas fa-bug'});
            });
        },
        closeHelpModalCreateSubAccountLeadConnector() {
            this.subAccountLeadConnector = [];
            this.subAccountLeadConnectorSelected = [];
            this.searchSubAccountLeadConnector
        },
        createClientFromSubAccountLeadConnector() {
            // sub account min 1
            if (this.subAccountLeadConnectorSelected.length <= 0) {
                return this.$notify({ type: 'danger', icon: 'tim-icons icon-bell-55', message: 'Please select at least 1 sub account' });
            }
            // sub account min 1
            
            // max 5 sub account
            if (this.subAccountLeadConnectorSelected.length > this.maxSelectedSubAccount) { // jika lebih dari selected account
                return this.$notify({ type: 'danger', icon: 'tim-icons icon-bell-55', message: 'You can select up to 5 sub accounts only' });
            }
            // max 5 sub account

            // create client from lead connector
            this.isGhlV2CreateClientFromSubAccount = true;
            this.$store.dispatch('createClientFromSubAccountLeadConnector', {
                company_id: this.$store.getters.userData.company_id,
                sub_accounts: this.subAccountLeadConnectorSelected,
                user_ip: this.$store.getters.userData.ip_login,
            })
            .then(response => {
                this.isGhlV2CreateClientFromSubAccount = false;
                this.modals.createSubAccountLeadConnector = false;
                this.closeHelpModalCreateSubAccountLeadConnector();
                return this.$notify({ type: 'success', icon: 'fas fa-save', message: 'Create client successfully' });
            })
            .catch(error => {
                this.isGhlV2CreateClientFromSubAccount = false;
                const message = error.response && error.response.data && error.response.data.message ? error.response.data.message : 'Something went wrong at create client';
                return this.$notify({ type: 'danger', icon: 'tim-icons icon-bell-55', message: message });
            });
            // create client from lead connector
        },
        setDefaultMinimumSpend(id) {
            this
            .$store
            .dispatch('setDefaultMinimumSpendConfig', {
                id: id,
                ipLogin: this.$store.getters.userData.ip_login,
            })
            .then(response => {
                // console.log(response);
                
                // sycn id default
                this.minimumSpendLists = this.minimumSpendLists.map(item => {
                    const same = String(item.id) === String(id);
                    return { ...item, isDefault: same };
                });

                this.$notify({ type: 'success', message: 'set default successfully', icon: 'fas fa-save'}); 
            })
            .catch(error => {
                console.error(error);
                const message = typeof(error.response.data.message) != 'undefined' ? error.response.data.message : 'Something Went Error';
                this.$notify({ type: 'danger', message: message, icon: 'fa fa-bug'});
            })
        },
        addMonth(type) {
            if (type == 'add') {
                if (this.months.length < this.monthMaxLengh) {
                    this.months.push('');
                    this.minimumspendError.monthlySpendList.push(false); 
                }
            } else if (type == 'edit') {
                if (this.monthsEdit.length < this.monthMaxLengh) {
                    this.monthsEdit.push('');
                    this.minimumspendErrorEdit.monthlySpendList.push(false); 
                }
            }
        },
        removeMonth(type, index) {
            if (type == 'add') {
                this.months.splice(index, 1);
                this.minimumspendError.monthlySpendList.splice(index, 1);
            } else if (type == 'edit') {
                this.monthsEdit.splice(index, 1);
                this.minimumspendErrorEdit.monthlySpendList.splice(index, 1);
            }
        },
        validateBetaFeature(type) {
            if(['b2b','b2b_module'].includes(type) && !this.$global.systemUser) {
                if(!this.$global.betaFeature.b2b_module.is_beta || this.$global.betaFeature.b2b_module.apply_to_all_agency || this.$global.isBeta) {
                    return true;
                } else {
                    return false;
                }
            } else if (['simplifi','simplifi_module'].includes(type) && !this.$global.systemUser) {
                if (!this.$global.betaFeature.simplifi_module.is_beta || this.$global.betaFeature.simplifi_module.apply_to_all_agency || this.$global.isBeta) {
                    return true;
                } else {
                    return false;
                }
            }
            return true;
        },
        scrollToSection(sectionId) {
            const element = document.getElementById(sectionId);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
                this.activeSection = sectionId;
            }
        },
        onScroll(){
            const sections = document.querySelectorAll('div[id]');
            const scrollPos = window.scrollY + 120;

            for (let section of sections) {
                if (
                     section.offsetTop <= scrollPos &&
                     section.offsetTop + section.offsetHeight > scrollPos
                ) { 
                    this.activeSection = section.id;
                    break;
                }
             }
        },
        openHelpModal(index){
            this.activeHelpItem = this.helpContentMap[index]
            this.modals.help = true;
        },
        formatPrice(value) {
            //let val = (value/1).toFixed(2).replace(',', '.')
            let val = (value/1).toFixed(2)
            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")
        },
        hasPassedFreeTrial() {
            const today = new Date();
            
            if (this.trialEndDate < today) {
                this.notPassSixtyDays = false;
            }else{
                this.notPassSixtyDays = true;
            }
        },
        test_smtpemail() {
            const hasChanged = Object.keys(this.customsmtp).some(
                key => this.customsmtp[key] !== this.prevcustomsmtp[key]
            );
            
            if (hasChanged) {
                const title = "SMTP Modified";
                const confirmButtonText = "Save & Send Test Email";

                swal.fire({
                    title: title,
                    text: 'Your email configuration has been modified. To send a test email, please save the updated settings first.',
                    icon: '',
                    showCancelButton: true,
                    customClass: {
                        confirmButton: 'btn btn-fill mr-3',
                        cancelButton: 'btn btn-danger btn-fill'
                    },
                    confirmButtonText: confirmButtonText,
                }).then(result => {
                    if (result.isConfirmed) {
                        this.save_general_smtpemail().then(() => {
                            this.test_smtpemail();
                        });
                    }
                });
                return;
            }

            if(this.customsmtp.default == false){
                const isInvalidEmailHost = this.validateEmailHost();
                // Validate email host
                if(!isInvalidEmailHost){
                    this.$notify({
                        type: 'danger',
                        message: 'Invalid host',
                        icon: 'tim-icons icon-bell-55'
                    });
    
                    return;
                }

                // if there are no fields filled in and choose an agency
                if(!this.customsmtp.port || !this.customsmtp.username || !this.customsmtp.password){
                    this.$notify({
                        type: 'danger',
                        message: 'Please fill all fields.',
                        icon: 'tim-icons icon-bell-55'
                    });
    
                    return;
                }
            }

            this.btnTestSMTP = 'Sending test email...';
            this.isSendingTestSMTP = true;
            this.$store.dispatch('testsmtp', {
                     companyID: this.userData.company_id,
                     emailsent: this.userData.email,
                 }).then(response => {
                    this.btnTestSMTP = 'Send test email';
                    this.isSendingTestSMTP = false;
                    
                    if (response.result == 'success') {
                        this.$notify({
                            type: 'success',
                            message: response.msg,
                            icon: 'tim-icons icon-bell-55'
                        }); 
                    }else{
                        this.$notify({
                            type: 'danger',
                            message: response.msg,
                            icon: 'tim-icons icon-bell-55'
                        }); 
                    }
                 },error => {
                    this.btnTestSMTP = 'Send test email';
                    this.isSendingTestSMTP = false;

                     this.$notify({
                            type: 'danger',
                            message: 'SMTP configuration failed to send the email',
                            icon: 'tim-icons icon-bell-55'
                        });  
                 });
        },
        revertViewMode() {
            const oriUsr = this.$global.getlocalStorage('userDataOri');
            //this.$global.SetlocalStorage('userData',oriUsr);
            localStorage.removeItem('userData');
            localStorage.removeItem('userDataOri');
            
            // localStorage.setItem('userData',JSON.stringify(oriUsr));
            this.$store.dispatch('updateUserData', oriUsr);
            localStorage.removeItem('userDataOri');
            localStorage.removeItem('subdomainAgency');
            localStorage.removeItem('rootcomp');
            this.$store.dispatch('setUserData', {
                    user: oriUsr,
            });
            window.document.location = "/configuration/agency-list/";
        },
        cancel_subscription() {
            this.confirmcancel = "";
            this.confirmcancelerror = false;
            this.modals.cancelsubscription = true;
        },
        process_cancelsubscription() {
            if (this.confirmcancel == "CANCEL") {
                $('#btnconfirmcancel').attr("disabled",true);
                this.modals.cancelsubscription = false;
                $('.processingArea').addClass('disabled-area');
                $('#popProcessing').show();
                
                this.$store.dispatch('cancelsubscription', {
                     companyID: this.userData.company_id,
                 }).then(response => {
                     if (response.result == 'success') {
                         this.revertViewMode();
                         return false;
                     }else{
                        ('#btnconfirmcancel').attr("disabled",false);
                        this.confirmcancelerror = false;
                        this.modals.cancelsubscription = false;
                        $('.processingArea').removeClass('disabled-area');
                        $('#popProcessing').hide();

                        this.$notify({
                            type: 'warning',
                            message: 'We are unable to process your subscription cancellation request at the moment. Please contact support for assistance.',
                            icon: 'tim-icons icon-bell-55'
                        });     

                     }
                 },error => {
                     ('#btnconfirmreset').attr("disabled",false);
                     this.confirmcancelerror = false;
                     this.modals.cancelsubscription = false;
                     $('.processingArea').removeClass('disabled-area');
                     $('#popProcessing').hide();

                     this.$notify({
                        type: 'warning',
                        message: 'We are unable to process your subscription cancellation request at the moment. Please contact support for assistance.',
                        icon: 'tim-icons icon-bell-55'
                    });   

                 });
            }else{
                this.confirmcancelerror = true;
            }
        },
        reset_stripeconnection() {
            this.confirmreset = "";
            this.confirmreseterror = false;
            this.modals.resetstripeconnection = true;
        },
        process_resetconnection() {
            if (this.confirmreset == "RESET") {
                $('#btnconfirmreset').attr("disabled",true);
                this.$store.dispatch('resetpaymentconnection', {
                     companyID: this.userData.company_id,
                     typeConnection: 'stripe',
                 }).then(response => {
                    if (response.result == 'success') {
                        localStorage.setItem('isRegisterAccount' , false)
                        localStorage.removeItem('isRegisterAccount');
                        this.checkConnectedAccount();
                        this.modals.resetstripeconnection = false;
                        this.$global.stripeaccountconnected = false;
                        swal.fire({ title : 'Success Reset', icon : 'success' });
                        window.location.reload();
                    } else {
                        ('#btnconfirmreset').attr("disabled",false);
                        this.confirmreseterror = true;
                    }
                 }, error => {
                    ('#btnconfirmreset').attr("disabled",false);
                    this.confirmreseterror = true;
                 });
            }else{
                this.confirmreseterror = true;
            }
        },
        getAgencyPlanPrice() {
            this.$store.dispatch('getGeneralSetting', {
                companyID: this.$global.idsys,
                settingname: 'agencyplan',
            }).then(response => {
               
                if (response.data != '') {

                    if (process.env.VUE_APP_DEVMODE == 'true') {
                        this.radios.nonwhitelabelling.monthly = response.data.testmode.nonwhitelabelling.monthly;
                        this.radios.nonwhitelabelling.monthlyprice = response.data.testmode.nonwhitelabelling.monthlyprice;
                        this.radios.nonwhitelabelling.yearly = response.data.testmode.nonwhitelabelling.yearly;
                        this.radios.nonwhitelabelling.yearlyprice = response.data.testmode.nonwhitelabelling.yearlyprice;
                        this.radios.whitelabeling.monthly = response.data.testmode.whitelabeling.monthly;
                        this.radios.whitelabeling.monthlyprice = response.data.testmode.whitelabeling.monthlyprice;
                        this.radios.whitelabeling.yearly = response.data.testmode.whitelabeling.yearly;
                        this.radios.whitelabeling.yearlyprice = response.data.testmode.whitelabeling.yearlyprice;
                        this.radios.freeplan = response.data.testmode.free;
                    }else{
                        this.radios.nonwhitelabelling.monthly = response.data.livemode.nonwhitelabelling.monthly;
                        this.radios.nonwhitelabelling.monthlyprice = response.data.livemode.nonwhitelabelling.monthlyprice;
                        this.radios.nonwhitelabelling.yearly = response.data.livemode.nonwhitelabelling.yearly;
                        this.radios.nonwhitelabelling.yearlyprice = response.data.livemode.nonwhitelabelling.yearlyprice;
                        this.radios.whitelabeling.monthly = response.data.livemode.whitelabeling.monthly;
                        this.radios.whitelabeling.monthlyprice = response.data.livemode.whitelabeling.monthlyprice;
                        this.radios.whitelabeling.yearly = response.data.livemode.whitelabeling.yearly;
                        this.radios.whitelabeling.yearlyprice = response.data.livemode.whitelabeling.yearlyprice;
                        this.radios.freeplan = response.data.livemode.free;
                    }

                }
            },error => {
                    
            });
        },
        getMinimumValueSimplifi() {
            this
            .$store
            .dispatch('getMinimumValueSimplifi', {
                idsys: this.$global.idsys
            })
            .then(response => {
                // console.log('getMinimumValuesSimplifi', { response });
                this.simplifiPriceRule.maxBid.minimum = response.data.maxBid.minimum;
                this.simplifiPriceRule.dailyBudget.minimum = response.data.dailyBudget.minimum;
            })
            .catch(error => {
                this.$notify({ type: 'danger', message: 'Something Went Wrong At Get Minimum Values Simplifi', icon: 'fas fa-bug' });
            })
        },
        initial_default_price() {
            this.resetAgencyCost();
            var _settingname = 'agencydefaultprice';
            if (this.$global.systemUser) {
                _settingname = 'rootcostagency';
            }

            this.$store.dispatch('getGeneralSetting', {
                companyID: this.userData.company_id,
                settingname: _settingname,
                idSys: this.$global.idsys
            }).then(response => {
                //console.log(response.data);
                if (response.data != '') {
                    this.costagency = response.data;
                    this.rootCostAgency = response.rootcostagency;
                    
                    this.selectsPaymentTerm.PaymentTermSelect = typeof(response.dpay) != 'undefined' ? response.dpay : 'Weekly';

                    if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                        this.LeadspeekPlatformFee = this.costagency.local.Weekly.LeadspeekPlatformFee;
                        this.LeadspeekCostperlead = this.costagency.local.Weekly.LeadspeekCostperlead;
                        this.LeadspeekCostperleadAdvanced = this.costagency.local.Weekly.LeadspeekCostperleadAdvanced;
                        this.LeadspeekMinCostMonth = this.costagency.local.Weekly.LeadspeekMinCostMonth;

                        this.LocatorPlatformFee  = this.costagency.locator.Weekly.LocatorPlatformFee;
                        this.LocatorCostperlead = this.costagency.locator.Weekly.LocatorCostperlead;
                        this.lead_FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                        this.LocatorMinCostMonth = this.costagency.locator.Weekly.LocatorMinCostMonth;

                        this.EnhancePlatformFee  = this.costagency.enhance.Weekly.EnhancePlatformFee;
                        this.EnhanceCostperlead = this.costagency.enhance.Weekly.EnhanceCostperlead;
                        this.EnhanceMinCostMonth = this.costagency.enhance.Weekly.EnhanceMinCostMonth;
                        
                        this.B2bPlatformFee  = this.costagency.b2b.Weekly.B2bPlatformFee;
                        this.B2bCostperlead = this.costagency.b2b.Weekly.B2bCostperlead;
                        this.B2bMinCostMonth = this.costagency.b2b.Weekly.B2bMinCostMonth;
                        
                        this.rootSiteIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Weekly) !== 'undefined')?this.rootCostAgency.local.Weekly.LeadspeekCostperlead:0;
                        this.rootSiteIDCostPerLeadAdvanced = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Weekly) !== 'undefined')?this.rootCostAgency.local.Weekly.LeadspeekCostperleadAdvanced:0;
                        this.rootSearchIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Weekly) !== 'undefined')?this.rootCostAgency.locator.Weekly.LocatorCostperlead:0;
                        this.rootEnhanceIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Weekly) !== 'undefined')?this.rootCostAgency.enhance.Weekly.EnhanceCostperlead:0;
                        this.rootB2bIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Weekly) !== 'undefined')?this.rootCostAgency.b2b.Weekly.B2bCostperlead:0;

                        this.m_LeadspeekPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Weekly) !== 'undefined')?this.rootCostAgency.local.Weekly.LeadspeekPlatformFee:0;
                        this.m_LeadspeekMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Weekly) !== 'undefined')?this.rootCostAgency.local.Weekly.LeadspeekMinCostMonth:0;
                        this.m_LeadspeekLocatorPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Weekly) !== 'undefined')?this.rootCostAgency.locator.Weekly.LocatorPlatformFee:0;
                        this.m_LeadspeekLocatorMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Weekly) !== 'undefined')?this.rootCostAgency.locator.Weekly.LocatorMinCostMonth:0;
                        this.m_LeadspeekEnhancePlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Weekly) !== 'undefined')?this.rootCostAgency.enhance.Weekly.EnhancePlatformFee:0;
                        this.m_LeadspeekEnhanceMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Weekly) !== 'undefined')?this.rootCostAgency.enhance.Weekly.EnhanceMinCostMonth:0;
                        this.m_LeadspeekB2BPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Weekly) !== 'undefined')?this.rootCostAgency.b2b.Weekly.B2bPlatformFee:0;
                        this.m_LeadspeekB2BMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Weekly) !== 'undefined')?this.rootCostAgency.b2b.Weekly.B2bMinCostMonth:0;

                    }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                        this.LeadspeekPlatformFee = this.costagency.local.Monthly.LeadspeekPlatformFee;
                        this.LeadspeekCostperlead = this.costagency.local.Monthly.LeadspeekCostperlead;
                        this.LeadspeekCostperleadAdvanced = this.costagency.local.Monthly.LeadspeekCostperleadAdvanced;
                        this.LeadspeekMinCostMonth = this.costagency.local.Monthly.LeadspeekMinCostMonth;

                        this.LocatorPlatformFee  = this.costagency.locator.Monthly.LocatorPlatformFee;
                        this.LocatorCostperlead = this.costagency.locator.Monthly.LocatorCostperlead;
                        this.lead_FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                        this.LocatorMinCostMonth = this.costagency.locator.Monthly.LocatorMinCostMonth;

                        this.EnhancePlatformFee  = this.costagency.enhance.Monthly.EnhancePlatformFee;
                        this.EnhanceCostperlead = this.costagency.enhance.Monthly.EnhanceCostperlead;
                        this.EnhanceMinCostMonth = this.costagency.enhance.Monthly.EnhanceMinCostMonth;

                        this.B2bPlatformFee  = this.costagency.b2b.Monthly.B2bPlatformFee;
                        this.B2bCostperlead = this.costagency.b2b.Monthly.B2bCostperlead;
                        this.B2bMinCostMonth = this.costagency.b2b.Monthly.B2bMinCostMonth;

                        this.rootSiteIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Monthly) !== 'undefined')?this.rootCostAgency.local.Monthly.LeadspeekCostperlead:0;
                        this.rootSiteIDCostPerLeadAdvanced = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Monthly) !== 'undefined')?this.rootCostAgency.local.Monthly.LeadspeekCostperleadAdvanced:0;
                        this.rootSearchIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Monthly) !== 'undefined')?this.rootCostAgency.locator.Monthly.LocatorCostperlead:0;
                        this.rootEnhanceIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Monthly) !== 'undefined')?this.rootCostAgency.enhance.Monthly.EnhanceCostperlead:0;
                        this.rootB2bIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Monthly) !== 'undefined')?this.rootCostAgency.b2b.Monthly.B2bCostperlead:0;

                        this.m_LeadspeekPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Monthly) !== 'undefined')?this.rootCostAgency.local.Monthly.LeadspeekPlatformFee:0;
                        this.m_LeadspeekMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Monthly) !== 'undefined')?this.rootCostAgency.local.Monthly.LeadspeekMinCostMonth:0;
                        this.m_LeadspeekLocatorPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Monthly) !== 'undefined')?this.rootCostAgency.locator.Monthly.LocatorPlatformFee:0;
                        this.m_LeadspeekLocatorMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Monthly) !== 'undefined')?this.rootCostAgency.locator.Monthly.LocatorMinCostMonth:0;
                        this.m_LeadspeekEnhancePlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Monthly) !== 'undefined')?this.rootCostAgency.enhance.Monthly.EnhancePlatformFee:0;
                        this.m_LeadspeekEnhanceMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Monthly) !== 'undefined')?this.rootCostAgency.enhance.Monthly.EnhanceMinCostMonth:0;
                        this.m_LeadspeekB2BPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Monthly) !== 'undefined')?this.rootCostAgency.b2b.Monthly.B2bPlatformFee:0;
                        this.m_LeadspeekB2BMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Monthly) !== 'undefined')?this.rootCostAgency.b2b.Monthly.B2bMinCostMonth:0;

                    }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
                        this.LeadspeekPlatformFee = this.costagency.local.OneTime.LeadspeekPlatformFee;
                        this.LeadspeekCostperlead = this.costagency.local.OneTime.LeadspeekCostperlead;
                        this.LeadspeekCostperleadAdvanced = this.costagency.local.OneTime.LeadspeekCostperleadAdvanced;
                        this.LeadspeekMinCostMonth = this.costagency.local.OneTime.LeadspeekMinCostMonth;

                        this.LocatorPlatformFee  = this.costagency.locator.OneTime.LocatorPlatformFee;
                        this.LocatorCostperlead = this.costagency.locator.OneTime.LocatorCostperlead;
                        this.lead_FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                        this.LocatorMinCostMonth = this.costagency.locator.OneTime.LocatorMinCostMonth;
                        
                        this.EnhancePlatformFee  = this.costagency.enhance.OneTime.EnhancePlatformFee;
                        this.EnhanceCostperlead = this.costagency.enhance.OneTime.EnhanceCostperlead;
                        this.EnhanceMinCostMonth = this.costagency.enhance.OneTime.EnhanceMinCostMonth;

                        this.B2bPlatformFee  = this.costagency.b2b.OneTime.B2bPlatformFee;
                        this.B2bCostperlead = this.costagency.b2b.OneTime.B2bCostperlead;
                        this.B2bMinCostMonth = this.costagency.b2b.OneTime.B2bMinCostMonth;

                        this.rootSiteIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.OneTime) !== 'undefined')?this.rootCostAgency.local.OneTime.LeadspeekCostperlead:0;
                        this.rootSiteIDCostPerLeadAdvanced = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.OneTime) !== 'undefined')?this.rootCostAgency.local.OneTime.LeadspeekCostperleadAdvanced:0;
                        this.rootSearchIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.OneTime) !== 'undefined')?this.rootCostAgency.locator.OneTime.LocatorCostperlead:0;
                        this.rootEnhanceIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.OneTime) !== 'undefined')?this.rootCostAgency.enhance.OneTime.EnhanceCostperlead:0;
                        this.rootB2bIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.OneTime) !== 'undefined')?this.rootCostAgency.b2b.OneTime.B2bCostperlead:0;

                        this.m_LeadspeekPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.OneTime) !== 'undefined')?this.rootCostAgency.local.OneTime.LeadspeekPlatformFee:0;
                        this.m_LeadspeekMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.OneTime) !== 'undefined')?this.rootCostAgency.local.OneTime.LeadspeekMinCostMonth:0;
                        this.m_LeadspeekLocatorPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.OneTime) !== 'undefined')?this.rootCostAgency.locator.OneTime.LocatorPlatformFee:0;
                        this.m_LeadspeekLocatorMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.OneTime) !== 'undefined')?this.rootCostAgency.locator.OneTime.LocatorMinCostMonth:0;
                        this.m_LeadspeekEnhancePlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.OneTime) !== 'undefined')?this.rootCostAgency.enhance.OneTime.EnhancePlatformFee:0;
                        this.m_LeadspeekEnhanceMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.OneTime) !== 'undefined')?this.rootCostAgency.enhance.OneTime.EnhanceMinCostMonth:0;
                        this.m_LeadspeekB2BPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.OneTime) !== 'undefined')?this.rootCostAgency.b2b.OneTime.B2bPlatformFee:0;
                        this.m_LeadspeekB2BMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.OneTime) !== 'undefined')?this.rootCostAgency.b2b.OneTime.B2bMinCostMonth:0;

                    }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {

                        this.LeadspeekPlatformFee = (typeof(this.costagency.local.Prepaid) !== 'undefined')?this.costagency.local.Prepaid.LeadspeekPlatformFee:0;
                        this.LeadspeekCostperlead = (typeof(this.costagency.local.Prepaid) !== 'undefined')?this.costagency.local.Prepaid.LeadspeekCostperlead:0;
                        this.LeadspeekCostperleadAdvanced = (typeof(this.costagency.local.Prepaid) != 'undefined')?this.costagency.local.Prepaid.LeadspeekCostperleadAdvanced:0;
                        this.LeadspeekMinCostMonth = (typeof(this.costagency.local.Prepaid) !== 'undefined')?this.costagency.local.Prepaid.LeadspeekMinCostMonth:0;

                        this.LocatorPlatformFee  = (typeof(this.costagency.locator.Prepaid) !== 'undefined')?this.costagency.locator.Prepaid.LocatorPlatformFee:0;
                        this.LocatorCostperlead = (typeof(this.costagency.locator.Prepaid) !== 'undefined')?this.costagency.locator.Prepaid.LocatorCostperlead:0;
                        this.lead_FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                        this.LocatorMinCostMonth = (typeof(this.costagency.locator.Prepaid) !== 'undefined')?this.costagency.locator.Prepaid.LocatorMinCostMonth:0;

                        this.EnhancePlatformFee  = (typeof(this.costagency.enhance.Prepaid) !== 'undefined')?this.costagency.enhance.Prepaid.EnhancePlatformFee:0;
                        this.EnhanceCostperlead = (typeof(this.costagency.enhance.Prepaid) !== 'undefined')?this.costagency.enhance.Prepaid.EnhanceCostperlead:0;
                        this.EnhanceMinCostMonth = (typeof(this.costagency.enhance.Prepaid) !== 'undefined')?this.costagency.enhance.Prepaid.EnhanceMinCostMonth:0;
                        
                        this.B2bPlatformFee  = (typeof(this.costagency.b2b.Prepaid) !== 'undefined')?this.costagency.b2b.Prepaid.B2bPlatformFee:0;
                        this.B2bCostperlead = (typeof(this.costagency.b2b.Prepaid) !== 'undefined')?this.costagency.b2b.Prepaid.B2bCostperlead:0;
                        this.B2bMinCostMonth = (typeof(this.costagency.b2b.Prepaid) !== 'undefined')?this.costagency.b2b.Prepaid.B2bMinCostMonth:0;

                        this.rootSiteIDCostPerLead =  (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekCostperlead:0; 
                        this.rootSiteIDCostPerLeadAdvanced = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekCostperleadAdvanced:0;
                        this.rootSearchIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Prepaid) !== 'undefined')?this.rootCostAgency.locator.Prepaid.LocatorCostperlead:0;
                        this.rootEnhanceIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Prepaid) !== 'undefined')?this.rootCostAgency.enhance.Prepaid.EnhanceCostperlead:0;
                        this.rootB2bIDCostPerLead = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Prepaid) !== 'undefined')?this.rootCostAgency.b2b.Prepaid.B2bCostperlead:0;

                        this.m_LeadspeekPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekPlatformFee:0;
                        this.m_LeadspeekMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekMinCostMonth:0;
                        this.m_LeadspeekLocatorPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Prepaid) !== 'undefined')?this.rootCostAgency.locator.Prepaid.LocatorPlatformFee:0;
                        this.m_LeadspeekLocatorMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Prepaid) !== 'undefined')?this.rootCostAgency.locator.Prepaid.LocatorMinCostMonth:0;
                        this.m_LeadspeekEnhancePlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Prepaid) !== 'undefined')?this.rootCostAgency.enhance.Prepaid.EnhancePlatformFee:0;
                        this.m_LeadspeekEnhanceMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Prepaid) !== 'undefined')?this.rootCostAgency.enhance.Prepaid.EnhanceMinCostMonth:0;
                        this.m_LeadspeekB2BPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Prepaid) !== 'undefined')?this.rootCostAgency.b2b.Prepaid.B2bPlatformFee:0;
                        this.m_LeadspeekB2BMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Prepaid) !== 'undefined')?this.rootCostAgency.b2b.Prepaid.B2bMinCostMonth:0;
                        
                        this.txtLeadService = "Monthly";

                    }

                    if (_settingname == 'agencydefaultprice') {
                        // for Simplifi Settings
                        const prepaidSimplifi = (this.costagency && this.costagency.simplifi && this.costagency.simplifi.Prepaid);
                        this.SimplifiMaxBid = (prepaidSimplifi && typeof(prepaidSimplifi.SimplifiMaxBid) != 'undefined')?prepaidSimplifi.SimplifiMaxBid:0;
                        this.SimplifiDailyBudget = (prepaidSimplifi && typeof(prepaidSimplifi.SimplifiDailyBudget) != 'undefined')?prepaidSimplifi.SimplifiDailyBudget:0;
                        this.SimplifiAgencyMarkup = (prepaidSimplifi && typeof(prepaidSimplifi.SimplifiAgencyMarkup) != 'undefined')?prepaidSimplifi.SimplifiAgencyMarkup:0;
                        // for Simplifi Settings
                    }

                    this.lead_FirstName_LastName = this.costagency.locatorlead.FirstName_LastName;
                    this.lead_FirstName_LastName_MailingAddress  = this.costagency.locatorlead.FirstName_LastName_MailingAddress;
                    //this.lead_FirstName_LastName_MailingAddress_Phone = this.costagency.locatorlead.FirstName_LastName_MailingAddress_Phone;

                    if(this.costagency && this.costagency.clean && typeof(this.costagency.clean.CleanCostperlead) != 'undefined' && this.$global.systemUser) {
                        this.CleanCostperlead = this.costagency.clean.CleanCostperlead;
                    }
                    if(this.costagency && this.costagency.clean && typeof(this.costagency.clean.CleanCostperleadAdvanced) != 'undefined' && this.$global.systemUser) {
                        this.CleanCostperleadAdvanced = this.costagency.clean.CleanCostperleadAdvanced;
                    }

                    this.prevDefaultRetailPrice = {
                        billingFrequency: this.selectsPaymentTerm.PaymentTermSelect,
                        
                        setupFeeLocal: this.LeadspeekPlatformFee,
                        campaignFeeLocal: this.LeadspeekMinCostMonth,
                        costPerLeadLocal: this.LeadspeekCostperlead,
                        costPerLeadAdvancedLocal: this.LeadspeekCostperleadAdvanced,

                        setupFeeLocator: this.LocatorPlatformFee,
                        campaignFeeLocator: this.LocatorMinCostMonth,
                        costPerLeadLocator: this.LocatorCostperlead,

                        setupFeeEnhance: this.EnhancePlatformFee,
                        campaignFeeEnhance: this.EnhanceMinCostMonth,
                        costPerLeadEnhance: this.EnhanceCostperlead,

                        setupFeeB2B: this.B2bPlatformFee,
                        campaignFeeB2B: this.B2bMinCostMonth,
                        costPerLeadB2B: this.B2bCostperlead,
                    }

                    //this.costagency.locatorlead.FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                }else if (response.rootcostagency != "") {
                    this.rootCostAgency = response.rootcostagency;
                    this.selectsPaymentTerm.PaymentTermSelect = typeof(response.dpay) != 'undefined' ? response.dpay : 'Weekly';
                    
                    if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                        this.rootSiteIDCostPerLead = this.rootCostAgency.local.Weekly.LeadspeekCostperlead;
                        this.rootSiteIDCostPerLeadAdvanced = this.rootCostAgency.local.Weekly.LeadspeekCostperleadAdvanced;
                        this.rootSearchIDCostPerLead = this.rootCostAgency.locator.Weekly.LocatorCostperlead;
                        this.rootEnhanceIDCostPerLead = this.rootCostAgency.enhance.Weekly.EnhanceCostperlead;
                        this.rootB2bIDCostPerLead = this.rootCostAgency.b2b.Weekly.B2bCostperlead;
                    }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                        this.rootSiteIDCostPerLead = this.rootCostAgency.local.Monthly.LeadspeekCostperlead;
                        this.rootSiteIDCostPerLeadAdvanced = this.rootCostAgency.local.Monthly.LeadspeekCostperleadAdvanced;
                        this.rootSearchIDCostPerLead = this.rootCostAgency.locator.Monthly.LocatorCostperlead;
                        this.rootEnhanceIDCostPerLead = this.rootCostAgency.enhance.Monthly.EnhanceCostperlead;
                        this.rootB2bIDCostPerLead = this.rootCostAgency.b2b.Monthly.B2bCostperlead;
                    }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                        this.rootSiteIDCostPerLead =  (typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekCostperlead:0; 
                        this.rootSiteIDCostPerLeadAdvanced = (typeof(this.rootCostAgency.local.Prepaid) != 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekCostperleadAdvanced:0;
                        this.rootSearchIDCostPerLead = (typeof(this.rootCostAgency.locator.Prepaid) !== 'undefined')?this.rootCostAgency.locator.Prepaid.LocatorCostperlead:0;
                        this.rootEnhanceIDCostPerLead = (typeof(this.rootCostAgency.enhance.Prepaid) !== 'undefined')?this.rootCostAgency.enhance.Prepaid.EnhanceCostperlead:0;
                        this.rootB2bIDCostPerLead = (typeof(this.rootCostAgency.b2b.Prepaid) !== 'undefined')?this.rootCostAgency.b2b.Prepaid.B2bCostperlead:0;
                    }
                }
                
            },error => {
                    
            });
        },
        save_default_subdomain() {
            // Define a regular expression for a valid subdomain
            const subdomainRegex = /^[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/;

            // Check if the entered subdomain matches the regular expression
            var isValidSubdomain = subdomainRegex.test(this.DownlineSubDomain);
           
            if (isValidSubdomain) {
            
                this.$store.dispatch('updateDefaultSubdomain', {
                    companyID: this.userData.company_id,
                    subdomain: this.DownlineSubDomain,
                    idsys: this.$global.idsys,
                }).then(response => {
                    if (response.result == "success") {
                        this.userData.subdomain = this.DownlineSubDomain;

                        const updatedData = {
                            subdomain: this.DownlineSubDomain
                        }
                        this.$store.dispatch('updateUserData', updatedData);

                        this.$notify({
                            type: 'success',
                            message: 'Default subdomain has been saved.',
                            icon: 'tim-icons icon-bell-55'
                        }); 

                        if (window.location.hostname != response.domain) {
                            window.document.location = 'https://' + this.DownlineSubDomain + '.' + this.$global.companyrootdomain.toLowerCase();
                        }
                    }else{
                        this.$notify({
                            type: 'warning',
                            message: response.message,
                            icon: 'tim-icons icon-bell-55'
                        });     
                    }
                },error => {
                    this.$notify({
                        type: 'warning',
                        message: 'We are unable to save your update, please try again later or contact the support',
                        icon: 'tim-icons icon-bell-55'
                    });     
                });
            }else{
                this.$notify({
                        type: 'danger',
                        message: 'Invalid subdomain name. Please enter a valid subdomain name.',
                        icon: 'tim-icons icon-bell-55'
                    });     
            }
        },
        save_default_price() {
            this.isLoadingDefaultRetailPrices = true
            var _settingname = 'agencydefaultprice';
            if (this.$global.systemUser) {
                _settingname = 'rootcostagency';
            }
            this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'customsmtpmodule',
                comsetname: _settingname,
                comsetval: this.costagency,
            }).then(response => {
                this.isLoadingDefaultRetailPrices = false 
                if (response.result == "success") {
                    this.$notify({
                        type: 'success',
                        message: 'Default Minimum Spend has been saved.',
                        icon: 'tim-icons icon-bell-55'
                    });  

                    //// for log users
                    let descriptionLogs = "";
                    const {  
                            billingFrequency,
                            setupFeeLocal, 
                            campaignFeeLocal, 
                            costPerLeadLocal, 
                            costPerLeadAdvancedLocal, 
                            setupFeeLocator, 
                            campaignFeeLocator, 
                            costPerLeadLocator, 
                            setupFeeEnhance, 
                            campaignFeeEnhance, 
                            costPerLeadEnhance,
                            setupFeeB2B,
                            campaignFeeB2B,
                            costPerLeadB2B
                        } 
                        = this.prevDefaultRetailPrice;
                    
                    const updates = [
                        { label: "Billing Frequency", prev: billingFrequency, new: this.selectsPaymentTerm.PaymentTermSelect },
                        { label: "Local One Time Setup Fee", prev: setupFeeLocal, new: this.LeadspeekPlatformFee },
                        { label: "Local Campaign Fee", prev: campaignFeeLocal, new: this.LeadspeekMinCostMonth },
                        { label: "Local Basic Cost", prev: costPerLeadLocal, new: this.LeadspeekCostperlead },
                        { label: "Local Advanced Cost", prev: costPerLeadAdvancedLocal, new: this.LeadspeekCostperleadAdvanced },

                        { label: "Locator One Time Setup Fee", prev: setupFeeLocator, new: this.LocatorPlatformFee },
                        { label: "Locator Campaign Fee", prev: campaignFeeLocator, new: this.LocatorMinCostMonth },
                        { label: "Locator Basic Cost", prev: costPerLeadLocator, new: this.LocatorCostperlead },

                        { label: "Enhance One Time Setup Fee", prev: setupFeeEnhance, new: this.EnhancePlatformFee },
                        { label: "Enhance Campaign Fee", prev: campaignFeeEnhance, new: this.EnhanceMinCostMonth },
                        { label: "Enhance Basic Cost", prev: costPerLeadEnhance, new: this.EnhanceCostperlead },

                        { label: "B2B One Time Setup Fee", prev: setupFeeB2B, new: this.B2bPlatformFee },
                        { label: "B2B Campaign Fee", prev: campaignFeeB2B, new: this.B2bMinCostMonth },
                        { label: "B2B Basic Cost", prev: costPerLeadB2B, new: this.B2bCostperlead },
                    ];

                    updates.forEach(({ label, prev, new: newValue }) => {
                        if (prev !== newValue) {
                            descriptionLogs += `${label} : Update values from ${prev} to ${newValue} | `;
                        } else {
                            if (label == 'Billing Frequency') {
                                descriptionLogs += `${label} : No changes, value remains ${prev} | `;
                            }
                        }
                    });

                    const uData = this.$store.getters.userData
                    this.$store.dispatch('onSaveUserLogs', {
                        desc: descriptionLogs,
                        action: "Update Default Retail Prices",
                        target_id: uData.id,
                    })

                    //// set prevDefaultRetailPrice ke data terbaru
                    const updateLogs = {
                        billingFrequency: this.selectsPaymentTerm.PaymentTermSelect,
                        
                        setupFeeLocal: this.LeadspeekPlatformFee,
                        campaignFeeLocal: this.LeadspeekMinCostMonth,
                        costPerLeadLocal: this.LeadspeekCostperlead,
                        costPerLeadAdvancedLocal: this.LeadspeekCostperleadAdvanced,

                        setupFeeLocator: this.LocatorPlatformFee,
                        campaignFeeLocator: this.LocatorMinCostMonth,
                        costPerLeadLocator: this.LocatorCostperlead,

                        setupFeeEnhance: this.EnhancePlatformFee,
                        campaignFeeEnhance: this.EnhanceMinCostMonth,
                        costPerLeadEnhance: this.EnhanceCostperlead,

                        setupFeeB2B: this.B2bPlatformFee,
                        campaignFeeB2B: this.B2bMinCostMonth,
                        costPerLeadB2B: this.B2bCostperlead,
                    }
                    this.$set(this, 'prevDefaultRetailPrice', updateLogs);

                }
            },error => {
                    this.isLoadingDefaultRetailPrices = false
            });
        },
        resetAgencyCost() {

            this.LeadspeekPlatformFee = '0';
            this.LeadspeekCostperlead = '0';
            this.LeadspeekMinCostMonth = '0';
            this.LocatorPlatformFee = '0';
            this.LocatorCostperlead = '0';
            this.LocatorMinCostMonth = '0';
            this.lead_FirstName_LastName = '0';
            this.lead_FirstName_LastName_MailingAddress = '0';
            this.lead_FirstName_LastName_MailingAddress_Phone = '0';

            this.costagency.local.Weekly.LeadspeekPlatformFee = '0';
            this.costagency.local.Weekly.LeadspeekCostperlead = '0';
            this.costagency.local.Weekly.LeadspeekCostperleadAdvanced = '0';
            this.costagency.local.Weekly.LeadspeekMinCostMonth = '0';

            this.costagency.local.Monthly.LeadspeekPlatformFee = '0';
            this.costagency.local.Monthly.LeadspeekCostperlead = '0';
            this.costagency.local.Monthly.LeadspeekCostperleadAdvanced = '0';
            this.costagency.local.Monthly.LeadspeekMinCostMonth = '0';

            this.costagency.local.OneTime.LeadspeekPlatformFee = '0';
            this.costagency.local.OneTime.LeadspeekCostperlead = '0';
            this.costagency.local.OneTime.LeadspeekCostperleadAdvanced = '0';
            this.costagency.local.OneTime.LeadspeekMinCostMonth = '0';

            if (typeof(this.costagency.local.Prepaid) !== 'undefined') {
                this.costagency.local.Prepaid.LeadspeekPlatformFee = '0';
                this.costagency.local.Prepaid.LeadspeekCostperlead = '0';
                this.costagency.local.Prepaid.LeadspeekCostperleadAdvanced = '0';
                this.costagency.local.Prepaid.LeadspeekMinCostMonth = '0';
            }

            this.costagency.locator.Weekly.LocatorPlatformFee = '0';
            this.costagency.locator.Weekly.LocatorCostperlead = '0';
            this.costagency.locator.Weekly.LocatorMinCostMonth = '0';

            this.costagency.locator.Monthly.LocatorPlatformFee = '0';
            this.costagency.locator.Monthly.LocatorCostperlead = '0';
            this.costagency.locator.Monthly.LocatorMinCostMonth = '0';

            this.costagency.locator.OneTime.LocatorPlatformFee = '0';
            this.costagency.locator.OneTime.LocatorCostperlead = '0';
            this.costagency.locator.OneTime.LocatorMinCostMonth = '0';

            if (typeof(this.costagency.locator.Prepaid) !== 'undefined') {
                this.costagency.locator.Prepaid.LocatorPlatformFee = '0';
                this.costagency.locator.Prepaid.LocatorCostperlead = '0';
                this.costagency.locator.Prepaid.LocatorMinCostMonth = '0';
            }
            
            this.costagency.locatorlead.FirstName_LastName = '0';
            this.costagency.locatorlead.FirstName_LastName_MailingAddress = '0';
            this.costagency.locatorlead.FirstName_LastName_MailingAddress_Phone = '0';

            this.costagency.enhance.Weekly.EnhancePlatformFee = '0';
            this.costagency.enhance.Weekly.EnhanceCostperlead = '0';
            this.costagency.enhance.Weekly.EnhanceMinCostMonth = '0';

            this.costagency.enhance.Monthly.EnhancePlatformFee = '0';
            this.costagency.enhance.Monthly.EnhanceCostperlead = '0';
            this.costagency.enhance.Monthly.EnhanceMinCostMonth = '0';

            this.costagency.enhance.OneTime.EnhancePlatformFee = '0';
            this.costagency.enhance.OneTime.EnhanceCostperlead = '0';
            this.costagency.enhance.OneTime.EnhanceMinCostMonth = '0';

            if (typeof(this.costagency.enhance.Prepaid) !== 'undefined') {
                this.costagency.enhance.Prepaid.EnhancePlatformFee = '0';
                this.costagency.enhance.Prepaid.EnhanceCostperlead = '0';
                this.costagency.enhance.Prepaid.EnhanceMinCostMonth = '0';
            }

            this.costagency.b2b.Weekly.B2bPlatformFee = '0';
            this.costagency.b2b.Weekly.B2bCostperlead = '0';
            this.costagency.b2b.Weekly.B2bMinCostMonth = '0';

            this.costagency.b2b.Monthly.B2bPlatformFee = '0';
            this.costagency.b2b.Monthly.B2bCostperlead = '0';
            this.costagency.b2b.Monthly.B2bMinCostMonth = '0';

            this.costagency.b2b.OneTime.B2bPlatformFee = '0';
            this.costagency.b2b.OneTime.B2bCostperlead = '0';
            this.costagency.b2b.OneTime.B2bMinCostMonth = '0';

            if (typeof(this.costagency.b2b.Prepaid) !== 'undefined') {
                this.costagency.b2b.Prepaid.B2bPlatformFee = '0';
                this.costagency.b2b.Prepaid.B2bCostperlead = '0';
                this.costagency.b2b.Prepaid.B2bMinCostMonth = '0';
            }
        },
        validateMinimumInput(event,type) {
            const handlers = {
                keyup: {
                    maxBid: () => {
                    	this.simplifiErrorInput.maxBid = (Number(this.SimplifiMaxBid) < Number(this.simplifiPriceRule.maxBid.minimum)) ?
                                                         (`*Max Bid Minimum $${this.simplifiPriceRule.maxBid.minimum}`) :
                                                         (``) ;
                    },
                    dailyBudget: () => {
                        this.simplifiErrorInput.dailyBudget = (Number(this.SimplifiDailyBudget) < Number(this.simplifiPriceRule.dailyBudget.minimum)) ?
                                                              (`*Daily Budget Minimum $${this.simplifiPriceRule.dailyBudget.minimum}`) :
                                                              (``) ;
                    }
                },
                blur: {
                    maxBid: () => {
                        this.simplifiErrorInput.maxBid = "";
                        this.SimplifiMaxBid = (Number(this.SimplifiMaxBid) < Number(this.simplifiPriceRule.maxBid.minimum)) ?
                                              (this.simplifiPriceRule.maxBid.minimum) :
                                              (this.SimplifiMaxBid) ;
                    },
                    dailyBudget: () => {
                        this.simplifiErrorInput.dailyBudget = "";
                        this.SimplifiDailyBudget = (Number(this.SimplifiDailyBudget) < Number(this.simplifiPriceRule.dailyBudget.minimum)) ?
                                                   (this.simplifiPriceRule.dailyBudget.minimum) :
                                                   (this.SimplifiDailyBudget) ;
                    }
                }
            }

            if (handlers[event] && handlers[event][type]) {
                handlers[event][type]();
            }
        },
        set_fee(type,typevalue) {
            // console.log(this.selectsPaymentTerm.PaymentTermSelect);
            // console.log(type);
            // console.log(typevalue);
            if (type == 'local') {

                if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                    if (typevalue == 'LeadspeekPlatformFee') {
                        this.costagency.local.Weekly.LeadspeekPlatformFee = this.LeadspeekPlatformFee;
                    }else if (typevalue == 'LeadspeekCostperlead') {
                        this.costagency.local.Weekly.LeadspeekCostperlead = this.LeadspeekCostperlead;
                    }else if (typevalue == 'LeadspeekCostperleadAdvanced') {
                        this.costagency.local.Weekly.LeadspeekCostperleadAdvanced = this.LeadspeekCostperleadAdvanced;
                    }else if (typevalue == 'LeadspeekMinCostMonth') {
                        this.costagency.local.Weekly.LeadspeekMinCostMonth = this.LeadspeekMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                    if (typevalue == 'LeadspeekPlatformFee') {
                        this.costagency.local.Monthly.LeadspeekPlatformFee = this.LeadspeekPlatformFee;
                    }else if (typevalue == 'LeadspeekCostperlead') {
                        this.costagency.local.Monthly.LeadspeekCostperlead = this.LeadspeekCostperlead;
                    }else if (typevalue == 'LeadspeekCostperleadAdvanced') {
                        this.costagency.local.Monthly.LeadspeekCostperleadAdvanced = this.LeadspeekCostperleadAdvanced;
                    }else if (typevalue == 'LeadspeekMinCostMonth') {
                        this.costagency.local.Monthly.LeadspeekMinCostMonth = this.LeadspeekMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
                    if (typevalue == 'LeadspeekPlatformFee') {
                        this.costagency.local.OneTime.LeadspeekPlatformFee = this.LeadspeekPlatformFee;
                    }else if (typevalue == 'LeadspeekCostperlead') {
                        this.costagency.local.OneTime.LeadspeekCostperlead = this.LeadspeekCostperlead;
                    }else if (typevalue == 'LeadspeekCostperleadAdvanced') {
                        this.costagency.local.OneTime.LeadspeekCostperleadAdvanced = this.LeadspeekCostperleadAdvanced;
                    }else if (typevalue == 'LeadspeekMinCostMonth') {
                        this.costagency.local.OneTime.LeadspeekMinCostMonth = this.LeadspeekMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                    if (typevalue == 'LeadspeekPlatformFee') {
                        this.costagency.local.Prepaid.LeadspeekPlatformFee = this.LeadspeekPlatformFee;
                    }else if (typevalue == 'LeadspeekCostperlead') {
                        this.costagency.local.Prepaid.LeadspeekCostperlead = this.LeadspeekCostperlead;
                    }else if (typevalue == 'LeadspeekCostperleadAdvanced') {
                        this.costagency.local.Prepaid.LeadspeekCostperleadAdvanced = this.LeadspeekCostperleadAdvanced;
                    }else if (typevalue == 'LeadspeekMinCostMonth') {
                        this.costagency.local.Prepaid.LeadspeekMinCostMonth = this.LeadspeekMinCostMonth;
                    }
                }

            }else if (type == 'locator') {

                if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                    if (typevalue == 'LocatorPlatformFee') {
                        this.costagency.locator.Weekly.LocatorPlatformFee = this.LocatorPlatformFee;
                    }else if (typevalue == 'LocatorCostperlead') {
                        this.costagency.locator.Weekly.LocatorCostperlead = this.LocatorCostperlead;
                    }else if (typevalue == 'LocatorMinCostMonth') {
                        this.costagency.locator.Weekly.LocatorMinCostMonth = this.LocatorMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                    if (typevalue == 'LocatorPlatformFee') {
                        this.costagency.locator.Monthly.LocatorPlatformFee = this.LocatorPlatformFee;
                    }else if (typevalue == 'LocatorCostperlead') {
                        this.costagency.locator.Monthly.LocatorCostperlead = this.LocatorCostperlead;
                    }else if (typevalue == 'LocatorMinCostMonth') {
                        this.costagency.locator.Monthly.LocatorMinCostMonth = this.LocatorMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
                    if (typevalue == 'LocatorPlatformFee') {
                        this.costagency.locator.OneTime.LocatorPlatformFee = this.LocatorPlatformFee;
                    }else if (typevalue == 'LocatorCostperlead') {
                        this.costagency.locator.OneTime.LocatorCostperlead = this.LocatorCostperlead;
                    }else if (typevalue == 'LocatorMinCostMonth') {
                        this.costagency.locator.OneTime.LocatorMinCostMonth = this.LocatorMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                    if (typevalue == 'LocatorPlatformFee') {
                        this.costagency.locator.Prepaid.LocatorPlatformFee = this.LocatorPlatformFee;
                    }else if (typevalue == 'LocatorCostperlead') {
                        this.costagency.locator.Prepaid.LocatorCostperlead = this.LocatorCostperlead;
                    }else if (typevalue == 'LocatorMinCostMonth') {
                        this.costagency.locator.Prepaid.LocatorMinCostMonth = this.LocatorMinCostMonth;
                    }
                }

            }else if (type == 'locatorlead') {
                if (typevalue == 'FirstName_LastName') {
                    this.costagency.locatorlead.FirstName_LastName = this.lead_FirstName_LastName;
                }else if (typevalue == 'FirstName_LastName_MailingAddress') {
                    this.costagency.locatorlead.FirstName_LastName_MailingAddress = this.lead_FirstName_LastName_MailingAddress;
                }else if (typevalue == 'FirstName_LastName_MailingAddress_Phone') {
                    this.costagency.locatorlead.FirstName_LastName_MailingAddress_Phone = this.lead_FirstName_LastName_MailingAddress_Phone;
                    if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                        this.costagency.locator.Weekly.LocatorCostperlead = this.lead_FirstName_LastName_MailingAddress_Phone;
                    }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                        this.costagency.locator.Monthly.LocatorCostperlead = this.lead_FirstName_LastName_MailingAddress_Phone;
                    }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
                        this.costagency.locator.OneTime.LocatorCostperlead = this.lead_FirstName_LastName_MailingAddress_Phone;
                    }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                        this.costagency.locator.Prepaid.LocatorCostperlead = this.lead_FirstName_LastName_MailingAddress_Phone;
                    }
                }
            }else if(type == 'enhance') {
                if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                    if (typevalue == 'EnhancePlatformFee') {
                        this.costagency.enhance.Weekly.EnhancePlatformFee = this.EnhancePlatformFee;
                    }else if (typevalue == 'EnhanceCostperlead') {
                        this.costagency.enhance.Weekly.EnhanceCostperlead = this.EnhanceCostperlead;
                    }else if (typevalue == 'EnhanceMinCostMonth') {
                    this.costagency.enhance.Weekly.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                    if (typevalue == 'EnhancePlatformFee') {
                        this.costagency.enhance.Monthly.EnhancePlatformFee = this.EnhancePlatformFee;
                    }else if (typevalue == 'EnhanceCostperlead') {
                        this.costagency.enhance.Monthly.EnhanceCostperlead = this.EnhanceCostperlead;
                    }else if (typevalue == 'EnhanceMinCostMonth') {
                        this.costagency.enhance.Monthly.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
                    if (typevalue == 'EnhancePlatformFee') {
                        this.costagency.enhance.OneTime.EnhancePlatformFee = this.EnhancePlatformFee;
                    }else if (typevalue == 'EnhanceCostperlead') {
                        this.costagency.enhance.OneTime.EnhanceCostperlead = this.EnhanceCostperlead;
                    }else if (typevalue == 'EnhanceMinCostMonth') {
                        this.costagency.enhance.OneTime.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                    if (typevalue == 'EnhancePlatformFee') {
                        this.costagency.enhance.Prepaid.EnhancePlatformFee = this.EnhancePlatformFee;
                    }else if (typevalue == 'EnhanceCostperlead') {
                        this.costagency.enhance.Prepaid.EnhanceCostperlead = this.EnhanceCostperlead;
                    }else if (typevalue == 'EnhanceMinCostMonth') {
                        this.costagency.enhance.Prepaid.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
                    }
                }
            }else if(type == 'b2b') {
                if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                    if (typevalue == 'B2bPlatformFee') {
                        this.costagency.b2b.Weekly.B2bPlatformFee = this.B2bPlatformFee;
                    }else if (typevalue == 'B2bCostperlead') {
                        this.costagency.b2b.Weekly.B2bCostperlead = this.B2bCostperlead;
                    }else if (typevalue == 'B2bMinCostMonth') {
                        this.costagency.b2b.Weekly.B2bMinCostMonth = this.B2bMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                    if (typevalue == 'B2bPlatformFee') {
                        this.costagency.b2b.Monthly.B2bPlatformFee = this.B2bPlatformFee;
                    }else if (typevalue == 'B2bCostperlead') {
                        this.costagency.b2b.Monthly.B2bCostperlead = this.B2bCostperlead;
                    }else if (typevalue == 'B2bMinCostMonth') {
                        this.costagency.b2b.Monthly.B2bMinCostMonth = this.B2bMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
                    if (typevalue == 'B2bPlatformFee') {
                        this.costagency.b2b.OneTime.B2bPlatformFee = this.B2bPlatformFee;
                    }else if (typevalue == 'B2bCostperlead') {
                        this.costagency.b2b.OneTime.B2bCostperlead = this.B2bCostperlead;
                    }else if (typevalue == 'B2bMinCostMonth') {
                        this.costagency.b2b.OneTime.B2bMinCostMonth = this.B2bMinCostMonth;
                    }
                }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                    if (typevalue == 'B2bPlatformFee') {
                        this.costagency.b2b.Prepaid.B2bPlatformFee = this.B2bPlatformFee;
                    }else if (typevalue == 'B2bCostperlead') {
                        this.costagency.b2b.Prepaid.B2bCostperlead = this.B2bCostperlead;
                    }else if (typevalue == 'B2bMinCostMonth') {
                        this.costagency.b2b.Prepaid.B2bMinCostMonth = this.B2bMinCostMonth;
                    }
                }
            }else if(type == 'simplifi') {
                if (typevalue == 'SimplifiMaxBid') {
                    this.costagency.simplifi.Prepaid.SimplifiMaxBid = this.SimplifiMaxBid;
                    this.validateMinimumInput('keyup', 'maxBid');
                } else if (typevalue == 'SimplifiDailyBudget') {
                    this.costagency.simplifi.Prepaid.SimplifiDailyBudget = this.SimplifiDailyBudget;
                    this.validateMinimumInput('keyup', 'dailyBudget');
                } else if (typevalue == 'SimplifiAgencyMarkup') {
                    this.costagency.simplifi.Prepaid.SimplifiAgencyMarkup = this.SimplifiAgencyMarkup;
                }
            }else if(type == 'clean') {
                if (typevalue == 'CleanCostperlead' && this.costagency && this.costagency.clean && typeof(this.costagency.clean.CleanCostperlead) !== 'undefined' && this.$global.systemUser) {
                    this.costagency.clean.CleanCostperlead = this.CleanCostperlead;
                } else if (typevalue == 'CleanCostperleadAdvanced' && this.costagency && this.costagency.clean && typeof(this.costagency.clean.CleanCostperleadAdvanced) != 'undefined' && this.$global.systemUser) {
                    this.costagency.clean.CleanCostperleadAdvanced = this.CleanCostperleadAdvanced;
                }
            }
        
        },
        paymentTermStatus() {
            //console.log(this.costagency);
            if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                this.txtLeadService = 'weekly';
                this.txtLeadIncluded = 'in that weekly charge';
                this.txtLeadOver ='from the weekly charge';

                /** SET VALUE */
                this.LeadspeekPlatformFee = this.costagency.local.Weekly.LeadspeekPlatformFee;
                this.LeadspeekCostperlead = this.costagency.local.Weekly.LeadspeekCostperlead;
                this.LeadspeekCostperleadAdvanced = this.costagency.local.Weekly.LeadspeekCostperleadAdvanced;
                this.LeadspeekMinCostMonth = this.costagency.local.Weekly.LeadspeekMinCostMonth;

                this.LocatorPlatformFee  = this.costagency.locator.Weekly.LocatorPlatformFee;
                this.LocatorCostperlead = this.costagency.locator.Weekly.LocatorCostperlead;
                this.lead_FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                this.LocatorMinCostMonth = this.costagency.locator.Weekly.LocatorMinCostMonth

                this.EnhancePlatformFee  = this.costagency.enhance.Weekly.EnhancePlatformFee;
                this.EnhanceCostperlead = this.costagency.enhance.Weekly.EnhanceCostperlead;
                this.EnhanceMinCostMonth = this.costagency.enhance.Weekly.EnhanceMinCostMonth

                this.B2bPlatformFee = this.costagency.b2b.Weekly.B2bPlatformFee;
                this.B2bCostperlead = this.costagency.b2b.Weekly.B2bCostperlead;
                this.B2bMinCostMonth = this.costagency.b2b.Weekly.B2bMinCostMonth;
                /** SET VALUE */

                this.rootSiteIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.local.Weekly) !== 'undefined')?this.rootCostAgency.local.Weekly.LeadspeekCostperlead:0;
                this.rootSiteIDCostPerLeadAdvanced = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Weekly) !== 'undefined')?this.rootCostAgency.local.Weekly.LeadspeekCostperleadAdvanced:0;
                this.rootSearchIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.locator.Weekly) !== 'undefined')?this.rootCostAgency.locator.Weekly.LocatorCostperlead:0;
                this.rootEnhanceIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.enhance.Weekly) !== 'undefined')?this.rootCostAgency.enhance.Weekly.EnhanceCostperlead:0;
                this.rootB2bIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.b2b.Weekly) !== 'undefined')?this.rootCostAgency.b2b.Weekly.B2bCostperlead:0;

                this.m_LeadspeekPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Weekly) !== 'undefined')?this.rootCostAgency.local.Weekly.LeadspeekPlatformFee:0;
                this.m_LeadspeekMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Weekly) !== 'undefined')?this.rootCostAgency.local.Weekly.LeadspeekMinCostMonth:0;
                this.m_LeadspeekLocatorPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Weekly) !== 'undefined')?this.rootCostAgency.locator.Weekly.LocatorPlatformFee:0;
                this.m_LeadspeekLocatorMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Weekly) !== 'undefined')?this.rootCostAgency.locator.Weekly.LocatorMinCostMonth:0;
                this.m_LeadspeekEnhancePlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Weekly) !== 'undefined')?this.rootCostAgency.enhance.Weekly.EnhancePlatformFee:0;
                this.m_LeadspeekEnhanceMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Weekly) !== 'undefined')?this.rootCostAgency.enhance.Weekly.EnhanceMinCostMonth:0;
                this.m_LeadspeekB2BPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Weekly) !== 'undefined')?this.rootCostAgency.b2b.Weekly.B2bPlatformFee:0;
                this.m_LeadspeekB2BMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Weekly) !== 'undefined')?this.rootCostAgency.b2b.Weekly.B2bMinCostMonth:0;

            }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                this.txtLeadService = 'monthly';
                this.txtLeadIncluded = 'in that monthly charge';
                this.txtLeadOver ='from the monthly charge';

                /** SET VALUE */
                this.LeadspeekPlatformFee = this.costagency.local.Monthly.LeadspeekPlatformFee;
                this.LeadspeekCostperlead = this.costagency.local.Monthly.LeadspeekCostperlead;
                this.LeadspeekCostperleadAdvanced = this.costagency.local.Monthly.LeadspeekCostperleadAdvanced;
                this.LeadspeekMinCostMonth = this.costagency.local.Monthly.LeadspeekMinCostMonth;
                
                this.LocatorPlatformFee  = this.costagency.locator.Monthly.LocatorPlatformFee;
                this.LocatorCostperlead = this.costagency.locator.Monthly.LocatorCostperlead;
                this.lead_FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                this.LocatorMinCostMonth = this.costagency.locator.Monthly.LocatorMinCostMonth
                
                this.EnhancePlatformFee  = this.costagency.enhance.Monthly.EnhancePlatformFee;
                this.EnhanceCostperlead = this.costagency.enhance.Monthly.EnhanceCostperlead;
                this.EnhanceMinCostMonth = this.costagency.enhance.Monthly.EnhanceMinCostMonth

                this.B2bPlatformFee = this.costagency.b2b.Monthly.B2bPlatformFee;
                this.B2bCostperlead = this.costagency.b2b.Monthly.B2bCostperlead;
                this.B2bMinCostMonth = this.costagency.b2b.Monthly.B2bMinCostMonth;
                /** SET VALUE */

                this.rootSiteIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.local.Monthly) !== 'undefined')?this.rootCostAgency.local.Monthly.LeadspeekCostperlead:0;
                this.rootSiteIDCostPerLeadAdvanced = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Monthly) !== 'undefined')?this.rootCostAgency.local.Monthly.LeadspeekCostperleadAdvanced:0;
                this.rootSearchIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.locator.Monthly) !== 'undefined')?this.rootCostAgency.locator.Monthly.LocatorCostperlead:0;
                this.rootEnhanceIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.enhance.Monthly) !== 'undefined')?this.rootCostAgency.enhance.Monthly.EnhanceCostperlead:0;
                this.rootB2bIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.b2b.Monthly) !== 'undefined')?this.rootCostAgency.b2b.Monthly.B2bCostperlead:0;

                this.m_LeadspeekPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Monthly) !== 'undefined')?this.rootCostAgency.local.Monthly.LeadspeekPlatformFee:0;
                this.m_LeadspeekMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Monthly) !== 'undefined')?this.rootCostAgency.local.Monthly.LeadspeekMinCostMonth:0;
                this.m_LeadspeekLocatorPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Monthly) !== 'undefined')?this.rootCostAgency.locator.Monthly.LocatorPlatformFee:0;
                this.m_LeadspeekLocatorMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Monthly) !== 'undefined')?this.rootCostAgency.locator.Monthly.LocatorMinCostMonth:0;
                this.m_LeadspeekEnhancePlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Monthly) !== 'undefined')?this.rootCostAgency.enhance.Monthly.EnhancePlatformFee:0;
                this.m_LeadspeekEnhanceMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Monthly) !== 'undefined')?this.rootCostAgency.enhance.Monthly.EnhanceMinCostMonth:0;
                this.m_LeadspeekB2BPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Monthly) !== 'undefined')?this.rootCostAgency.b2b.Monthly.B2bPlatformFee:0;
                this.m_LeadspeekB2BMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Monthly) !== 'undefined')?this.rootCostAgency.b2b.Monthly.B2bMinCostMonth:0;

            }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
                this.txtLeadService = '';
                this.txtLeadIncluded = '';
                this.txtLeadOver ='';

                /** SET VALUE */
                this.LeadspeekPlatformFee = this.costagency.local.OneTime.LeadspeekPlatformFee;
                this.LeadspeekCostperlead = this.costagency.local.OneTime.LeadspeekCostperlead;
                this.LeadspeekCostperleadAdvanced = this.costagency.local.OneTime.LeadspeekCostperleadAdvanced;
                this.LeadspeekMinCostMonth = this.costagency.local.OneTime.LeadspeekMinCostMonth;
                
                this.LocatorPlatformFee  = this.costagency.locator.OneTime.LocatorPlatformFee;
                this.LocatorCostperlead = this.costagency.locator.OneTime.LocatorCostperlead;
                this.lead_FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                this.LocatorMinCostMonth = this.costagency.locator.OneTime.LocatorMinCostMonth
                
                this.EnhancePlatformFee  = this.costagency.enhance.OneTime.EnhancePlatformFee;
                this.EnhanceCostperlead = this.costagency.enhance.OneTime.EnhanceCostperlead;
                this.EnhanceMinCostMonth = this.costagency.enhance.OneTime.EnhanceMinCostMonth

                this.B2bPlatformFee = this.costagency.b2b.OneTime.B2bPlatformFee;
                this.B2bCostperlead = this.costagency.b2b.OneTime.B2bCostperlead;
                this.B2bMinCostMonth = this.costagency.b2b.OneTime.B2bMinCostMonth;
                /** SET VALUE */

                this.rootSiteIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.local.OneTime) !== 'undefined')?this.rootCostAgency.local.OneTime.LeadspeekCostperlead:0;
                this.rootSiteIDCostPerLeadAdvanced = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.OneTime) !== 'undefined')?this.rootCostAgency.local.OneTime.LeadspeekCostperleadAdvanced:0;
                this.rootSearchIDCostPerLead = (this.rootCostAgency != "" &&  typeof(this.rootCostAgency.locator.OneTime) !== 'undefined')?this.rootCostAgency.locator.OneTime.LocatorCostperlead:0;
                this.rootEnhanceIDCostPerLead = (this.rootCostAgency != "" &&  typeof(this.rootCostAgency.enhance.OneTime) !== 'undefined')?this.rootCostAgency.enhance.OneTime.EnhanceCostperlead:0;
                this.rootB2bIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.b2b.OneTime) !== 'undefined')?this.rootCostAgency.b2b.OneTime.B2bCostperlead:0;

                this.m_LeadspeekPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.OneTime) !== 'undefined')?this.rootCostAgency.local.OneTime.LeadspeekPlatformFee:0;
                this.m_LeadspeekMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.OneTime) !== 'undefined')?this.rootCostAgency.local.OneTime.LeadspeekMinCostMonth:0;
                this.m_LeadspeekLocatorPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.OneTime) !== 'undefined')?this.rootCostAgency.locator.OneTime.LocatorPlatformFee:0;
                this.m_LeadspeekLocatorMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.OneTime) !== 'undefined')?this.rootCostAgency.locator.OneTime.LocatorMinCostMonth:0;
                this.m_LeadspeekEnhancePlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.OneTime) !== 'undefined')?this.rootCostAgency.enhance.OneTime.EnhancePlatformFee:0;
                this.m_LeadspeekEnhanceMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.OneTime) !== 'undefined')?this.rootCostAgency.enhance.OneTime.EnhanceMinCostMonth:0;
                this.m_LeadspeekB2BPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.OneTime) !== 'undefined')?this.rootCostAgency.b2b.OneTime.B2bPlatformFee:0;
                this.m_LeadspeekB2BMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.OneTime) !== 'undefined')?this.rootCostAgency.b2b.OneTime.B2bMinCostMonth:0;

            }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                this.txtLeadService = 'monthly';
                this.txtLeadIncluded = '';
                this.txtLeadOver ='';

                if (typeof(this.costagency.local.Prepaid) == 'undefined') {
                    this.$set(this.costagency.local,'Prepaid',{
                    LeadspeekPlatformFee: '0',
                    LeadspeekCostperlead: '0',
                    LeadspeekCostperleadAdvance: '0',
                    LeadspeekMinCostMonth: '0',
                    });
                }

                if (typeof(this.costagency.locator.Prepaid) == 'undefined') {
                    this.$set(this.costagency.locator,'Prepaid',{
                    LocatorPlatformFee: '0',
                    LeadspeekCostperleadAdvance: '0',
                    LocatorCostperlead: '0',
                    LocatorMinCostMonth: '0',
                    });
                }

                /** SET VALUE */
                this.LeadspeekPlatformFee = this.costagency.local.Prepaid.LeadspeekPlatformFee;
                this.LeadspeekCostperlead = this.costagency.local.Prepaid.LeadspeekCostperlead;
                this.LeadspeekCostperleadAdvanced = this.costagency.local.Prepaid.LeadspeekCostperleadAdvanced;
                this.LeadspeekMinCostMonth = this.costagency.local.Prepaid.LeadspeekMinCostMonth;
                
                this.LocatorPlatformFee  = this.costagency.locator.Prepaid.LocatorPlatformFee;
                this.LocatorCostperlead = this.costagency.locator.Prepaid.LocatorCostperlead;
                this.lead_FirstName_LastName_MailingAddress_Phone = this.LocatorCostperlead;
                this.LocatorMinCostMonth = this.costagency.locator.Prepaid.LocatorMinCostMonth
                
                this.EnhancePlatformFee  = this.costagency.enhance.Prepaid.EnhancePlatformFee;
                this.EnhanceCostperlead = this.costagency.enhance.Prepaid.EnhanceCostperlead;
                this.EnhanceMinCostMonth = this.costagency.enhance.Prepaid.EnhanceMinCostMonth

                this.B2bPlatformFee = this.costagency.b2b.Prepaid.B2bPlatformFee;
                this.B2bCostperlead = this.costagency.b2b.Prepaid.B2bCostperlead;
                this.B2bMinCostMonth = this.costagency.b2b.Prepaid.B2bMinCostMonth;
                /** SET VALUE */

                this.rootSiteIDCostPerLead =  (this.rootCostAgency != "" && typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekCostperlead:0; 
                this.rootSiteIDCostPerLeadAdvanced = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekCostperleadAdvanced:0;
                this.rootSearchIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.locator.Prepaid) !== 'undefined')?this.rootCostAgency.locator.Prepaid.LocatorCostperlead:0;
                this.rootEnhanceIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.enhance.Prepaid) !== 'undefined')?this.rootCostAgency.enhance.Prepaid.EnhanceCostperlead:0;
                this.rootB2bIDCostPerLead = (this.rootCostAgency != "" && typeof(this.rootCostAgency.b2b.Prepaid) !== 'undefined')?this.rootCostAgency.b2b.Prepaid.B2bCostperlead:0;

                this.m_LeadspeekPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekPlatformFee:0;
                this.m_LeadspeekMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.local.Prepaid) !== 'undefined')?this.rootCostAgency.local.Prepaid.LeadspeekMinCostMonth:0;
                this.m_LeadspeekLocatorPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Prepaid) !== 'undefined')?this.rootCostAgency.locator.Prepaid.LocatorPlatformFee:0;
                this.m_LeadspeekLocatorMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.locator.Prepaid) !== 'undefined')?this.rootCostAgency.locator.Prepaid.LocatorMinCostMonth:0;
                this.m_LeadspeekEnhancePlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Prepaid) !== 'undefined')?this.rootCostAgency.enhance.Prepaid.EnhancePlatformFee:0;
                this.m_LeadspeekEnhanceMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.enhance.Prepaid) !== 'undefined')?this.rootCostAgency.enhance.Prepaid.EnhanceMinCostMonth:0;
                this.m_LeadspeekB2BPlatformFee = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Prepaid) !== 'undefined')?this.rootCostAgency.b2b.Prepaid.B2bPlatformFee:0;
                this.m_LeadspeekB2BMinCostMonth = (this.rootCostAgency != '' && typeof(this.rootCostAgency.b2b.Prepaid) !== 'undefined')?this.rootCostAgency.b2b.Prepaid.B2bMinCostMonth:0;

            }
        },
        paymentTermChange() {
            this.paymentTermStatus();

            this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'paymenttermDefault',
                paymenttermDefault: this.selectsPaymentTerm.PaymentTermSelect,
            }).then(response => {
                if (response.result == "success") {
                    this.userData.paymentterm_default = this.selectsPaymentTerm.PaymentTermSelect;

                    const updatedData = {
                        paymentterm_default: this.selectsPaymentTerm.PaymentTermSelect,
                    }
                    this.$store.dispatch('updateUserData', updatedData);

                    this.$notify({
                        type: 'success',
                        message: 'Default Payment Term has been saved.',
                        icon: 'tim-icons icon-bell-55'
                    });  

                    //// for log users
                    let descriptionLogs = "";
                    const { billingFrequency} = this.prevDefaultRetailPrice;
                    
                    const updates = [
                        { label: "Billing Frequency", prev: billingFrequency, new: this.selectsPaymentTerm.PaymentTermSelect },
                    ];

                    updates.forEach(({ label, prev, new: newValue }) => {
                        if (prev !== newValue) {
                            descriptionLogs += `${label} : Update values from ${prev} to ${newValue} `;
                        } else {
                            descriptionLogs += `${label} : No changes, value remains ${prev}`;
                        }
                    });

                    const uData = this.$store.getters.userData
                    this.$store.dispatch('onSaveUserLogs', {
                        desc: descriptionLogs,
                        action: "Billing Frequency Agency Change",
                        target_id: uData.id,
                    });

                    Object.assign(this.prevDefaultRetailPrice, {
                        billingFrequency: this.selectsPaymentTerm.PaymentTermSelect,
                    })
                }
            },error => {
                        
            });

        },
        save_plan_package() {
            if (this.radios.packageID != '' && this.radios.packageID != this.radios.lastpackageID) {
                swal.fire({
                        title: 'Please Confirm',
                        text: 'Your new plan will begin immediately.',
                        icon: '',
                        showCancelButton: true,
                        customClass: {
                        confirmButton: 'btn btn-fill mr-3',
                        cancelButton: 'btn btn-danger btn-fill'
                        },
                        confirmButtonText: 'Choose Plan',
                        buttonsStyling: false
                }).then(result => {
                        if (result.isDismissed) {
                            this.radios.packageID = this.radios.lastpackageID;
                        }else if (result.isConfirmed) {
                            this.process_save_plan();
                        }
                });
            }
        },
        process_save_plan() {
            if (this.radios.packageID != '' && this.radios.packageID != this.radios.lastpackageID) {
                this.$store.dispatch('savePlanPackage', {
                    CompanyID: this.userData.company_id,
                    packageID: this.radios.packageID,
                }).then(response => {
                    //console.log(response);
                    if (response.result == 'success') {
                        this.radios.lastpackageID = this.radios.packageID;
                        this.enable_disabled_package(this.radios.packageID);
                        if (response.packagewhite == 'F') {
                            this.Whitelabellingstatus = false;
                        }else{
                            this.Whitelabellingstatus = true;
                        }

                        if (response.plannextbill != '') { 
                            this.plannextbill = response.plannextbill;
                        }

                        this.is_whitelabeling = response.is_whitelabeling
                        
                        this.$notify({
                            type: 'success',
                            message: 'Your plan has been updated',
                            icon: 'tim-icons icon-bell-55'
                        });  
                    }else{
                        this.$notify({
                            type: 'warning',
                            message: 'We are unable to save your plan, please try again later or contact the support',
                            icon: 'tim-icons icon-bell-55'
                        });  
                    }
                },error => { 
                    this.radios.packageID = this.radios.lastpackageID;
                    this.$notify({
                        type: 'warning',
                        message: 'We are unable to save your plan, please try again later or contact the support',
                        icon: 'tim-icons icon-bell-55'
                    });  
                });
            }
        },
        insertShortCode(text) {
            var areaId = this.activeElement;
            
            if (areaId == 'emailsubject' || areaId == 'fromName') {
                const shortcode = text;
                const textField = $('#' + areaId)[0]; // Get the raw DOM element

                const startPos = textField.selectionStart || 0;
                const endPos = textField.selectionEnd || 0;
                
                const currentValue = textField.value;
                const newValue = currentValue.substring(0, startPos) + shortcode + currentValue.substring(endPos);
                
                textField.value = newValue;
                textField.setSelectionRange(startPos + shortcode.length, startPos + shortcode.length);
                
                // Trigger input event manually to update Vue or other frameworks
                $(textField).trigger('input');
                if (areaId == 'emailsubject') {
                    this.emailtemplate.subject = $(textField).val();
                }else if (areaId == 'fromName') {
                    this.emailtemplate.fromName = $(textField).val();
                }
                $(textField).focus();
            }else if (areaId == 'emailcontent') {
                var txtarea = document.getElementById(areaId);
                var strPos = 0;
                var br = ((txtarea.selectionStart || txtarea.selectionStart == '0') ?
                            "ff" : (document.selection ? "ie" : false));
                if (br == "ie") {
                    txtarea.focus();
                    var range = document.selection.createRange();
                    range.moveStart('character', -txtarea.value.length);
                    strPos = range.text.length;
                } else if (br == "ff") strPos = txtarea.selectionStart;

                var front = (txtarea.value).substring(0, strPos);
                var back = (txtarea.value).substring(strPos, txtarea.value.length);
                txtarea.value = front + text + back;
                strPos = strPos + text.length;
                if (br == "ie") {
                    txtarea.focus();
                    var range = document.selection.createRange();
                    range.moveStart('character', -txtarea.value.length);
                    range.moveStart('character', strPos);
                    range.moveEnd('character', 0);
                    range.select();
                } else if (br == "ff") {
                    txtarea.selectionStart = strPos;
                    txtarea.selectionEnd = strPos;
                    txtarea.focus();
                }

                this.emailtemplate.content = txtarea.value;
            }
            return false;
        },
        get_email_template(templateName) {
            if (templateName == "forgetpassword") {
                this.emailtemplate.title = "Forget password Template"
                this.emailupdatemodule = 'em_forgetpassword';

                this.$store.dispatch('getGeneralSetting', {
                    companyID: this.userData.company_id,
                    settingname: 'em_forgetpassword',
                }).then(response => {
                    if (response.data != '') {
                        if (typeof(response.data.fromAddress) != 'undefined') {
                            this.emailtemplate.fromAddress = response.data.fromAddress;
                        }
                        if (typeof(response.data.fromName) != 'undefined') {
                            this.emailtemplate.fromName = response.data.fromName;
                        }
                        if (typeof(response.data.fromReplyto) != 'undefined') {
                            this.emailtemplate.fromReplyto = response.data.fromReplyto;
                        }
                        this.emailtemplate.subject = response.data.subject;
                        this.emailtemplate.content = response.data.content;
                    }
                },error => {
                  
                });

                this.modals.emailtemplate = true;
            }else if (templateName == "clientwelcome") {
                this.emailtemplate.title = "Account setup template"
                this.emailupdatemodule = 'em_clientwelcomeemail';

                this.$store.dispatch('getGeneralSetting', {
                    companyID: this.userData.company_id,
                    settingname: 'em_clientwelcomeemail',
                }).then(response => {
                    if (response.data != '') {
                        if (typeof(response.data.fromAddress) != 'undefined') {
                            this.emailtemplate.fromAddress = response.data.fromAddress;
                        }
                        if (typeof(response.data.fromName) != 'undefined') {
                            this.emailtemplate.fromName = response.data.fromName;
                        }
                        if (typeof(response.data.fromReplyto) != 'undefined') {
                            this.emailtemplate.fromReplyto = response.data.fromReplyto;
                        }
                        this.emailtemplate.subject = response.data.subject;
                        this.emailtemplate.content = response.data.content;
                    }
                },error => {
                  
                });

                this.modals.emailtemplate = true;
            }else if (templateName == "agencyclientwelcome") {
                this.emailtemplate.title = "Agency account setup template"
                this.emailupdatemodule = 'em_agencywelcomeemail';

                this.$store.dispatch('getGeneralSetting', {
                    companyID: this.userData.company_id,
                    settingname: 'em_agencywelcomeemail',
                }).then(response => {
                    if (response.data != '') {
                        if (typeof(response.data.fromAddress) != 'undefined') {
                            this.emailtemplate.fromAddress = response.data.fromAddress;
                        }
                        if (typeof(response.data.fromName) != 'undefined') {
                            this.emailtemplate.fromName = response.data.fromName;
                        }
                        if (typeof(response.data.fromReplyto) != 'undefined') {
                            this.emailtemplate.fromReplyto = response.data.fromReplyto;
                        }
                        this.emailtemplate.subject = response.data.subject;
                        this.emailtemplate.content = response.data.content;
                    }
                },error => {
                  
                });

                this.modals.emailtemplate = true;
            }else if (templateName == "campaigncreated") {
                this.emailtemplate.title = "Campaign Create template"
                this.emailupdatemodule = 'em_campaigncreated';

                this.$store.dispatch('getGeneralSetting', {
                    companyID: this.userData.company_id,
                    settingname: 'em_campaigncreated',
                }).then(response => {
                    if (response.data != '') {
                        if (typeof(response.data.fromAddress) != 'undefined') {
                            this.emailtemplate.fromAddress = response.data.fromAddress;
                        }
                        if (typeof(response.data.fromName) != 'undefined') {
                            this.emailtemplate.fromName = response.data.fromName;
                        }
                        if (typeof(response.data.fromReplyto) != 'undefined') {
                            this.emailtemplate.fromReplyto = response.data.fromReplyto;
                        }
                        this.emailtemplate.subject = response.data.subject;
                        this.emailtemplate.content = response.data.content;
                    }
                },error => {
                  
                });

                this.modals.emailtemplate = true;
            }else if (templateName == "billingunsuccessful") {
                this.emailtemplate.title = "Billing Unsuccessful template"
                this.emailupdatemodule = 'em_billingunsuccessful';

                this.$store.dispatch('getGeneralSetting', {
                    companyID: this.userData.company_id,
                    settingname: 'em_billingunsuccessful',
                }).then(response => {
                    if (response.data != '') {
                        if (typeof(response.data.fromAddress) != 'undefined') {
                            this.emailtemplate.fromAddress = response.data.fromAddress;
                        }
                        if (typeof(response.data.fromName) != 'undefined') {
                            this.emailtemplate.fromName = response.data.fromName;
                        }
                        if (typeof(response.data.fromReplyto) != 'undefined') {
                            this.emailtemplate.fromReplyto = response.data.fromReplyto;
                        }
                        this.emailtemplate.subject = response.data.subject;
                        this.emailtemplate.content = response.data.content;
                    }
                },error => {
                  
                });

                this.modals.emailtemplate = true;
            }else if (templateName == "archivecampaign") {
                this.emailtemplate.title = "Campaign Archived template"
                this.emailupdatemodule = 'em_archivecampaign';

                this.$store.dispatch('getGeneralSetting', {
                    companyID: this.userData.company_id,
                    settingname: 'em_archivecampaign',
                }).then(response => {
                    if (response.data != '') {
                        if (typeof(response.data.fromAddress) != 'undefined') {
                            this.emailtemplate.fromAddress = response.data.fromAddress;
                        }
                        if (typeof(response.data.fromName) != 'undefined') {
                            this.emailtemplate.fromName = response.data.fromName;
                        }
                        if (typeof(response.data.fromReplyto) != 'undefined') {
                            this.emailtemplate.fromReplyto = response.data.fromReplyto;
                        }
                        this.emailtemplate.subject = response.data.subject;
                        this.emailtemplate.content = response.data.content;
                    }
                },error => {
                  
                });

                this.modals.emailtemplate = true;
            }else if (templateName == "prepaidtopuptwodaylimitclient") {
                this.emailtemplate.title = "Campaign Prepaid Limit Template"
                this.emailupdatemodule = 'em_prepaidtopuptwodaylimitclient';

                this.$store.dispatch('getGeneralSetting', {
                    companyID: this.userData.company_id,
                    settingname: 'em_prepaidtopuptwodaylimitclient',
                }).then(response => {
                    if (response.data != '') {
                        if (typeof(response.data.fromAddress) != 'undefined') {
                            this.emailtemplate.fromAddress = response.data.fromAddress;
                        }
                        if (typeof(response.data.fromName) != 'undefined') {
                            this.emailtemplate.fromName = response.data.fromName;
                        }
                        if (typeof(response.data.fromReplyto) != 'undefined') {
                            this.emailtemplate.fromReplyto = response.data.fromReplyto;
                        }
                        this.emailtemplate.subject = response.data.subject;
                        this.emailtemplate.content = response.data.content;
                    }
                },error => {
                  
                });

                this.modals.emailtemplate = true;
            }
        },
        createConnectedAccountLink() {
            this.$store.dispatch('createConnectedAccountLink', {
                connectid: this.accConID,
                refreshurl: this.refreshURL,
                returnurl: this.returnURL,
                idsys: this.$global.idsys,
            }).then(response => {
                document.location = response.params.url;
            },error => {
                this.txtStatusConnectedAccount = "Setup your stripe account";
                this.DisabledBtnConnectedAccount = false;
                this.$notify({
                    type: 'warning',
                    message: 'we are unable to connect your account, please try again later or contact the support',
                    icon: 'tim-icons icon-bell-55'
                });  
            });
        },
        createConnectedAccount() {
            this.txtStatusConnectedAccount = "Connecting...";
            this.DisabledBtnConnectedAccount = true;

            this.$store.dispatch('createConnectedAccount', {
                companyID: this.userData.company_id,
                companyname: this.userData.company_name,
                companyphone: this.userData.company_phone,
                companyaddress: this.userData.company_address,
                companycity: this.userData.company_city,
                companystate: this.userData.company_state,
                companyzip: this.userData.company_zip,
                companycountry: this.userData.company_country_code,
                companyemail: this.userData.company_email,
                //weburl: (this.userData.domain != '')?this.userData.domain:this.userData.subdomain,
                weburl: window.location.hostname,
                idsys: this.$global.idsys,
            }).then(response => {
                this.accConID = response.params.ConnectAccID;
                this.createConnectedAccountLink();
            },error => {
                this.txtStatusConnectedAccount = "Setup your stripe account";
                this.DisabledBtnConnectedAccount = false;
                this.$notify({
                    type: 'warning',
                    message: 'we are unable to connect your account, with following message: ' + error,
                    icon: 'tim-icons icon-bell-55',
                    duration: 8000,
                });  

            });
        },
        processConnectedAccount() {
            if (this.ActionBtnConnectedAccount == 'createAccount') {
                this.createConnectedAccount();
            }else if (this.ActionBtnConnectedAccount == 'createAccountLink') {
                this.createConnectedAccountLink();
            }
        },
        processExsistingConnectedAccount() {
            this.txtStatusConnectedExistingAccount = 'Connecting...';
            this.DisabledBtnConnectedAccount = true;
            
            this
            .$store
            .dispatch('createExistingConnectedAccountLink', {
                company_root_id: this.$global.idsys,
                company_id: this.userData.company_id,
                email: this.userData.email,
                user_id: this.userData.id,
                user_type: this.userData.user_type,
                subdomain: window.location.href,
            })
            .then(response => {
                // console.log(response);
                if(response.url) {
                    document.location = response.url;
                } else {
                    this.$notify({ type: 'danger', message: 'url for setup stripe empty, please try again later or contact the support', icon: 'tim-icons icon-bell-55'});  
                }
            })
            .catch(error => {
                // console.error(error);
                this.txtStatusConnectedExistingAccount = 'Connect existing stripe account';
                this.DisabledBtnConnectedAccount = false;
                this.$notify({ type: 'danger', message: 'we are unable to connect your account, please try again later or contact the support', icon: 'tim-icons icon-bell-55'});  
            });
        },
        showError(){
            const htmlContent = this.txtErrorRequirements + `<br/><br/>To update your Stripe connected account <a class="text-underline-color" style="text-decoration: underline;" href="https://dashboard.stripe.com/account/status" target="_blank">Click here</a>`;
            swal.fire({
                html: htmlContent,
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: 'btn-black-color'
                }
            });
        },
        checkConnectedAccount() {
            this.$store.dispatch('checkConnectedAccount', {
                companyID: this.userData.company_id,
                idsys: this.$global.idsys,
            }).then(response => {
                if (this.defaultPaymentMethod == 'stripe') {
                    if (this.$refs.btnglobalreset) {
                        this.$refs.btnglobalreset.style.display = 'block';
                    }
                }
                if (response.result == 'failed') {
                    this.txtStatusConnectedAccount = "Setup your stripe account";
                    this.ActionBtnConnectedAccount = 'createAccount';
                    this.statusColorConnectedAccount = '';
                    if (this.userData.manual_bill == 'T') {
                        this.radios.lastpackageID = 'agencyDirectPayment';
                        this.radios.packageID = 'agencyDirectPayment';
                        this.Whitelabellingstatus = true;
                        this.is_whitelabeling = response.is_whitelabeling
                        this.$global.statusaccountconnected = 'completed';
                    }
                }else if (response.result == 'pending') {
                    this.txtStatusConnectedAccount = 'Your stripe registration is incomplete, click here to continue';
                    this.ActionBtnConnectedAccount = 'createAccountLink';
                    this.accConID = response.params[0].acc_connect_id;
                    this.statusColorConnectedAccount = '';
                }else if (response.result == 'pending-verification' || response.result == 'inverification') {
                    this.txtStatusConnectedAccount = 'Almost there, stripe is verifying your account.';
                    this.ActionBtnConnectedAccount = 'inverification';
                    this.accConID = response.params[0].acc_connect_id;
                    this.statusColorConnectedAccount = '#fb6340';
                }else{
                    this.txtStatusConnectedAccount = "Stripe Account Connected"
                    this.ActionBtnConnectedAccount = 'accountConnected';
                    this.statusColorConnectedAccount = '#2dce89';
                    this.$global.stripeaccountconnected = true;
                    this.txtPayoutsEnabled = response.payouts_enabled; 
                    this.txtpaymentsEnabled = response.charges_enabled; 
                    this.txtErrorRequirements = response.account_requirements.errors.length > 0 ? response.account_requirements.errors[0].reason : '';
                    this.$global.statusaccountconnected = 'completed';
                    $('#popstatusaccountconnect').hide();

                    if (response.params[0]['package_id'] == '') {
                        this.Whitelabellingstatus = false;
                    }else{
                        if (response.paymentgateway != 'stripe') {
                            this.defaultPaymentMethod = response.paymentgateway;  
                        }
                       
                        if (typeof(response.packagename) != 'undefined' && response.packagename != '') {
                            this.packageName = response.packagename;
                        }

                        this.radios.packageID = response.params[0]['package_id'];
                        this.radios.lastpackageID = response.params[0]['package_id'];

                        if (response.openallplan == 'F') {
                            this.enable_disabled_package(response.params[0]['package_id']);
                        }else{
                            this.openallplan();
                        }

                        if (response.plannextbill != '') {
                            this.plannextbill = response.plannextbill;
                        }

                        if (response.packagewhite == 'T') {
                            this.Whitelabellingstatus = true;
                        }

                        this.is_whitelabeling = response.is_whitelabeling
                    }
                    
                }


            },error => {
                if (this.$refs.btnglobalreset) {
                    this.$refs.btnglobalreset.style.display = 'block';
                }
            });
        },
        openallplan() {
            this.radios.nonwhitelabelling.monthly_disabled = false;
            this.radios.nonwhitelabelling.yearly_disabled = false;
            this.radios.whitelabeling.monthly_disabled = false;
            this.radios.whitelabeling.yearly_disabled = false;
        },
        enable_disabled_package(currPlan) {
            /** FOR INITIAL PACKAGE PLAN */
            this.openallplan();
        if (process.env.VUE_APP_DEVMODE == 'true') {
            if (currPlan == this.radios.nonwhitelabelling.yearly) {
                this.radios.nonwhitelabelling.monthly_disabled = true;
                this.radios.whitelabeling.monthly_disabled = true;
            }else if (currPlan == this.radios.whitelabeling.monthly) {
                this.radios.nonwhitelabelling.monthly_disabled = true;
                this.radios.nonwhitelabelling.yearly_disabled = true;
            }else if (currPlan == this.radios.whitelabeling.yearly) {
                this.radios.nonwhitelabelling.monthly_disabled = true;
                this.radios.nonwhitelabelling.yearly_disabled = true;
                this.radios.whitelabeling.monthly_disabled = true;
            }
            
        }else{
            if (currPlan == this.radios.nonwhitelabelling.yearly) {
                this.radios.nonwhitelabelling.monthly_disabled = true;
                this.radios.whitelabeling.monthly_disabled = true;
            }else if (currPlan == this.radios.whitelabeling.monthly) {
                this.radios.nonwhitelabelling.monthly_disabled = true;
                this.radios.nonwhitelabelling.yearly_disabled = true;
            }else if (currPlan == this.radios.whitelabeling.yearly) {
                this.radios.nonwhitelabelling.monthly_disabled = true;
                this.radios.nonwhitelabelling.yearly_disabled = true;
                this.radios.whitelabeling.monthly_disabled = true;
            }
        }
        /** FOR INITIAL PACKAGE PLAN */
        },
        checkGoogleConnect() {
            this.$store.dispatch('checkGoogleConnectSheet', {
                companyID: this.userData.company_id,
            }).then(response => {
                //console.log(response.googleSpreadsheetConnected);
                if (response.googleSpreadsheetConnected) {
                    this.GoogleConnectTrue = true;
                    this.GoogleConnectFalse = false;
                }else{
                    this.GoogleConnectTrue = false;
                    this.GoogleConnectFalse = true;
                }
            },error => {
                
            });
        },
        disconnect_googleSpreadSheet() {
            swal.fire({
                    title: 'Please Confirm',
                    text: 'Are you sure you want to disconnect from Google Sheets? Please ensure that after disconnecting, you reconnect with the same Google account you previously used. If you connect with a different account, your current or old campaigns may not function properly, but your new campaigns will work with the newly connected Google account. Additionally, please make sure you check all permissions required when you connect your Google account. Are you sure you want to proceed?',
                    icon: '',
                    showCancelButton: true,
                    customClass: {
                    confirmButton: 'btn btn-fill mr-3',
                    cancelButton: 'btn btn-danger btn-fill'
                    },
                    confirmButtonText: 'Disconnect Google Sheet',
                    buttonsStyling: false
            }).then(result => {
                    if (result.isConfirmed) {
                       this.process_disconnect_googleSpreadSheet();
                    }
            });

            
        },
        process_disconnect_googleSpreadSheet() {
            this.$store.dispatch('disconectGoogleSheet', {
                companyID: this.userData.company_id,
            }).then(response => {
                //console.log(response.googleSpreadsheetConnected);
                if (response.result == 'success') {
                    this.GoogleConnectTrue = false;
                    this.GoogleConnectFalse = true;
                    setTimeout(() => {
                         this.$notify({
                              type: 'success',
                              message: 'Success Disconnect Google Sheet.',
                              icon: 'tim-icons icon-bell-55'
                             });  
                        }, 500)
                    this.$store.dispatch('onSaveUserLogs', {
                        desc: "Google Spreadsheet disconnected",
                        action: "Disconnect Google Sheet",
                        target_id: this.userData.id,
                    })

                }else{
                    swal.fire({
                        title: 'Information',
                        text: response.message,
                        timer: 7000,
                        showConfirmButton: false,
                        icon: 'success'
                    });
            }
            },error => {
                
          });
        },
        // scrollToSection(sectionId) {
        //   this.$nextTick(() => {
        //     const element = document.getElementById(sectionId);
        //     if (element) {
        //       element.scrollIntoView({ behavior: 'smooth' });
        //       this.activeSection = sectionId;
        //     }
        //   });
        // },
        connect_googleSpreadSheet() {
            window.removeEventListener('message', this.callbackGoogleConnected);
            window.addEventListener('message', this.callbackGoogleConnected);

            var left = (screen.width/2)-(1024/2);
            var top = (screen.height/2)-(800/2);
            var fbwindow = window.open(process.env.VUE_APP_DATASERVER_URL + '/auth/google-spreadSheet/' + this.userData.company_id,'Google SpreadSheet Auth',"menubar=no,toolbar=no,status=no,width=640,height=800,toolbar=no,location=no,modal=1,left="+left+",top="+top);
        },
        callbackGoogleConnected(e) {
            window.removeEventListener('message', this.callbackGoogleConnected);
            if (e.origin == process.env.VUE_APP_DATASERVER_URL) {
                if (e.data == 'success') {
                    this.GoogleConnectTrue = true;
                    this.GoogleConnectFalse = false;

                    this.$store.dispatch('onSaveUserLogs', {
                        desc: "Google Spreadsheet connected",
                        action: "Connect Google Sheet",
                        target_id: this.userData.id,
                    })
                }
            }
        },
        syncGlobalModulNameLink() {
            this.$global.globalModulNameLink.local.name = this.leadsLocalName;
            this.$global.globalModulNameLink.local.url = this.leadsLocalUrl;
            
            this.$global.globalModulNameLink.locator.name = this.leadsLocatorName;
            this.$global.globalModulNameLink.locator.url = this.leadsLocatorUrl;
            
            this.$global.globalModulNameLink.enhance.name = this.leadsEnhanceName;
            this.$global.globalModulNameLink.enhance.url = this.leadsEnhanceUrl;
            
            this.$global.globalModulNameLink.b2b.name = this.leadsB2bName;
            this.$global.globalModulNameLink.b2b.url = this.leadsB2bUrl;

            this.$global.globalModulNameLink.simplifi.name = this.leadsSimplifiName;
            this.$global.globalModulNameLink.simplifi.url = this.leadsSimplifiUrl;
        },
        showProgress(index) {
            $('#progressmsgshow' + index + ' .progress').find('.progress-bar').css('width', '0%');
            $('#progressmsgshow' + index + ' .progress').find('.progress-bar').html('0%');
            $('#progressmsgshow' + index + ' .progress').find('.progress-bar').removeClass('bg-success');
            $('#progressmsgshow' + index + ' .progress').show();
            $('#progressmsgshow' + index + '').show();
        },
        updateProgress(index,value) {
            $('#progressmsgshow' + index + ' .progress').find('.progress-bar').css('width', `${value}%`)
            $('#progressmsgshow' + index + ' .progress').find('.progress-bar').html(`${value}%`)
        },
        hideProgress(index) {
            $('#progressmsgshow' + index + ' .progress').hide();
            $('#progressmsgshow' + index + '').hide();
        },

        changefont(fontname,event) {
            $('body').css('font-family',fontname);
            $('.fontoption').each(function(i, el) {
                $(el).removeClass('fontactive')
            });
           
            $(event.target).parent().addClass('fontactive');
            this.fontthemeactive = $(event.target).parent().attr('id');
        },
        reverthistory(revertkey) {
            if (revertkey == 'sidebar') {
                // $('#sidebarcolor').val(this.sidebarcolor);
                this.colors.sidebar = this.sidebarcolor;
                $('.sidebar').css('background', this.sidebarcolor);
                $('head').append('<style>.sidebar:before{border-bottom-color:' + this.sidebarcolor + ' !important;}</style>');
                document.documentElement.style.setProperty('--bg-bar-color', this.sidebarcolor);
            }else if (revertkey == 'template') {
                $('#backgroundtemplatecolor').val(this.backgroundtemplatecolor);
                $('.main-panel').css('background',this.backgroundtemplatecolor);
            }
            // else if (revertkey == 'box') {
            //     $('#boxcolor').val(this.boxcolor);
            //     $('.card').css('background', this.boxcolor);
            //     $('.card-body').css('background', this.boxcolor);
            // }
            else if (revertkey == 'text') {
                // $('#textcolor').val(this.textcolor);
                this.colors.text = this.textcolor;
                $('#cssGlobalTextColor').remove();
                $('head').append('<style id="cssGlobalTextColor">.sidebar-wrapper a span small, .sidebar-wrapper #sidebarCompanyName, .sidebar-menu-item p, .company-select-tag, .sidebar-normal {color:' + this.textcolor + ' !important;}</style>');
                document.documentElement.style.setProperty('--text-bar-color', this.textcolor);
            }else if (revertkey == 'link') {
                $('#linkcolor').val(this.linkcolor);
                $('#cssGlobalLinkColor').remove();
                $('head').append('<style id="cssGlobalLinkColor">body a, a span {color:' + this.linkcolor + ' !important;}</style>');
            }
        },
        get_agency_embeddedcode() {
            var _settingname = 'rootAgencyEmbeddedCode';

            this.$store.dispatch('getGeneralSetting', {
                companyID: this.userData.company_id,
                settingname: _settingname,
            }).then(response => {
                if (response.data != '') {
                    this.agencyEmbeddedCode.embeddedcode = response.data.embeddedcode;
                    this.agencyEmbeddedCode.placeEmbedded = response.data.placeEmbedded;
                }
            },error => {
                  
            });
        },
        get_smtp_setting() {
            var _settingname = 'customsmtpmenu';
            if (this.$global.systemUser) {
                _settingname = 'rootsmtp';
            }
            this.$store.dispatch('getGeneralSetting', {
                companyID: this.userData.company_id,
                settingname: _settingname,
            }).then(response => {
                if (response.data != '') {
                    this.customsmtp.default = response.data.default;
                    this.customsmtp.host = response.data.host;
                    this.customsmtp.port = response.data.port;
                    this.customsmtp.username = response.data.username;
                    this.customsmtp.password = response.data.password;

                    this.prevcustomsmtp.default = response.data.default;
                    this.prevcustomsmtp.host = response.data.host;
                    this.prevcustomsmtp.port = response.data.port;
                    this.prevcustomsmtp.username = response.data.username;
                    this.prevcustomsmtp.password = response.data.password;
                    
                    if (typeof(response.data.security) == 'undefined') {
                        response.data.security = 'ssl';
                    }else if (response.data.security == null) {
                        response.data.security = "none";
                    }
                    this.customsmtp.security = response.data.security;
                }
            },error => {
                  
            });
        },
        test_email_content() {
            this.btnTestEmail = 'Sending test email...';
            this.isSendingTestEmail = true;
            // Data to be sent for testing the email
            var emailData = {
            fromAddress: (this.customsmtp.host) ? this.customsmtp.host :this.emailtemplate.fromAddress,
            fromName: (this.customsmtp.username) ? this.customsmtp.username :this.emailtemplate.fromName,
            fromReplyto: this.emailtemplate.fromReplyto,
            subject: this.emailtemplate.subject,
            content: this.emailtemplate.content,
            testEmailAddress: this.userData.email,
            companyID: this.userData.company_id,
            companyParentID: this.userData.company_parent,
            userType: this.userData.user_type
            };
            // Send request to backend to test email via Vuex store
            this.$store.dispatch('testEmail', emailData)
            .then(response => {
                this.$notify({
                type: 'success',
                message: 'Test email sent successfully',
                icon: 'far fa-check-circle'
                });
                this.isSendingTestEmail = false;
                this.btnTestEmail = 'Send Test Email';
            })
            .catch(error => {
                this.$notify({
                type: 'danger',
                message: 'Failed to send test email',
                icon: 'far fa-times-circle'
                });
                console.error(error);
                this.isSendingTestEmail = false;
                this.btnTestEmail = 'Send Test Email';
            });
        },

        save_email_content() {
            var templateName = this.emailupdatemodule
            this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'customsmtpmodule',
                comsetname: templateName,
                comsetval: this.emailtemplate,
            }).then(response => {
                if (response.result == "success") {
                    this.$notify({
                        type: 'success',
                        message: 'Setting has been saved.',
                        icon: 'tim-icons icon-bell-55'
                    });  

                    this.modals.emailtemplate = false;
                }
            },error => {
                        
                this.$notify({
                    type: 'danger',
                    message: 'Failed to update email content',
                    icon: 'far fa-times-circle'
                });
            });
        },
        isValidDomain(v) {
            if (!v) return false;
            var re = /^(?!:\/\/)([a-zA-Z0-9-]+\.){0,5}[a-zA-Z0-9-][a-zA-Z0-9-]+\.[a-zA-Z]{2,64}?$/gi;
            return re.test(v);
        },
        check_whitelabelling_fields() {
            var pass = true;
            if(this.chkagreewl == true) {
                if(this.DownlineDomain == "" || !this.isValidDomain(this.DownlineDomain)) {
                    pass = false;
                    //$('#dwdomain').parent().addClass('has-danger');
                    this.$notify({
                        type: 'danger',
                        message: 'Invalid domain name. Please enter a valid domain name.',
                        icon: 'tim-icons icon-bell-55'
                    });     
                }else{
                    $('#dwdomain').parent().removeClass('has-danger');
                }
            }
           
            /*if(this.chkagreewl == false) {
                pass = false;
                this.agreewhitelabelling = true;
            }else{
                this.agreewhitelabelling = false;
            }*/

            return pass;
        },
        save_general_whitelabelling() {
            if (this.check_whitelabelling_fields()) {
            //if (true) {
                this.chkagreewl = true;

                /** CHECK IF A RECORD POINTED */
                swal.fire({
                    title: '',
                    html: 'Have you followed the steps before saving it?<br/><small>* The process may take a few minutes. Please refresh the page to check its status.</small>',
                    icon: '',
                    showCancelButton: true,
                    customClass: {
                    confirmButton: 'btn btn-fill mr-3',
                    cancelButton: 'btn btn-danger btn-fill'
                    },
                    confirmButtonText: `Yes, I have`,
                    cancelButtonText: `No, I haven't`,
                    buttonsStyling: false
                }).then(result => {
                    if (result.isDismissed) {
                        this.isLoadingWhiteLabelingDomain = false;
                        return false;
                    }
                        else if (result.isConfirmed) {
                            this.isLoadingWhiteLabelingDomain = true 
                            /** PROCESS SAVE */
                            this.$store.dispatch('updateCustomDomain', {
                            companyID: this.userData.company_id,
                            DownlineDomain: this.DownlineDomain,
                            whitelabelling: this.chkagreewl,
                            }).then(response => {
                                if (response.result == "success") {
                                    var typecolor = ''
                                    if (response.activated == 'T') {
                                        typecolor = 'success';
                                        this.Whitelabellingstatus = true;
                                        this.chkagreewl = true;
                                        this.userData.whitelabelling = 'T';
                                    }else{
                                        typecolor = 'danger';
                                        this.Whitelabellingstatus = false;
                                        //this.chkagreewl = false;
                                        this.userData.whitelabelling = 'F';
                                    }

                                    this.userData.domain = response.domain;
                                    
                                    const updatedData = {
                                        whitelabelling: this.userData.whitelabelling,
                                        domain: response.domain
                                    }

                                    this.$store.dispatch('updateUserData', updatedData);

                                    this.$notify({
                                        type: typecolor,
                                        message: response.message,
                                        icon: 'tim-icons icon-bell-55'
                                    });  
                                        this.isLoadingWhiteLabelingDomain = false 
                                }else{
                                    this.$notify({
                                        type: 'danger',
                                        message: response.message,
                                        icon: 'tim-icons icon-bell-55'
                                    });  
                                        this.isLoadingWhiteLabelingDomain = false 
                                }
                            },error => {
                                       this.isLoadingWhiteLabelingDomain = false 
                            }).catch(() => {
                                 this.isLoadingWhiteLabelingDomain = false 
                            });
                            /** PROCESS SAVE */
                        }
                });
                /** CHECK IF A RECORD POINTED */
            }
            return false;
        },
        save_general_agencyembeddedcode() {
            this.isLoadingEmbeddedSupportWidget = true
            var _comsetname = 'rootAgencyEmbeddedCode';
            this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'customsmtpmodule',
                comsetname: _comsetname,
                comsetval: this.agencyEmbeddedCode,
            }).then(response => {
                if (response.result == "success") {
                    this.$notify({
                        type: 'success',
                        message: 'Setting has been saved.',
                        icon: 'tim-icons icon-bell-55'
                    });  
                    this.isLoadingEmbeddedSupportWidget = false
                }
            },error => {
                this.isLoadingEmbeddedSupportWidget = false
            });
        },
        save_client_isDeleteStatus(){
            this.isLoadingDeleteClientStatus = true
            var _comsetname = 'agencyClientDeletedAccount';
            this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'agencyClientDeletedAccount',
                comsetname: null,
                comsetval: this.enabledDeletedAccountClient,
            }).then(response => {
                if (response.result == "success") {
                    this.$notify({
                        type: 'success',
                        message: 'Setting has been saved.',
                        icon: 'tim-icons icon-bell-55'
                    });  
                    this.isLoadingDeleteClientStatus = false
                }
            },error => {
                this.isLoadingDeleteClientStatus = false
            });
        },
        save_general_smtpemail() {
            this.isLoadingEmailSettings = true
            var _comsetname = 'customsmtpmenu';
            if (this.$global.systemUser) {
                _comsetname = 'rootsmtp';
            }

            // if the fields are filled in and he activates it to enterprise
            if(this.customsmtp.default == true){
                this.customsmtp.host = '';
                this.customsmtp.port = '';
                this.customsmtp.username = '';
                this.customsmtp.password = '';
            }

            // if there are no fields filled in
            if (!this.customsmtp.host && !this.customsmtp.port && !this.customsmtp.username && !this.customsmtp.password) {
                this.customsmtp.default = true;
            } else {
                this.customsmtp.default = false;
            }

            // if choose an agency
            if(this.customsmtp.default == false){
                const isInvalidEmailHost = this.validateEmailHost();
                // Validate email host
                if(!isInvalidEmailHost){
                    this.$notify({
                        type: 'danger',
                        message: 'Invalid host',
                        icon: 'tim-icons icon-bell-55'
                    });
    
                    return;
                }

                // if there are no fields filled in and choose an agency
                if(!this.customsmtp.port || !this.customsmtp.username || !this.customsmtp.password){
                    this.$notify({
                        type: 'danger',
                        message: 'Please fill all fields.',
                        icon: 'tim-icons icon-bell-55'
                    });
    
                    return;
                }
            }


            return this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'customsmtpmodule',
                comsetname: _comsetname,
                comsetval: this.customsmtp,
            }).then(response => {
                if (response.result == "success") {
                    this.$notify({
                        type: 'success',
                        message: 'Setting has been saved.',
                        icon: 'tim-icons icon-bell-55'
                    });  

                    // Deep copy to prevent shared reference
                    this.prevcustomsmtp = JSON.parse(JSON.stringify(this.customsmtp));
                }
                    this.isLoadingEmailSettings = false

            },error => {
                    this.isLoadingEmailSettings = false
            });
        },
        validateProductname(){
            if(this.leadsLocalName.trim() == '' || this.leadsLocalUrl.trim() == '' || this.leadsLocatorName.trim() == '' || this.leadsLocatorUrl.trim() == '')	{
                this.$notify({
                        type: 'primary',
                        message: 'All fields are mandatory.',
                        icon: 'fas fa-bug'
                    });  
                return false 
            } 
            
            if(this.$global.globalModulNameLink.enhance.name != null && this.$global.globalModulNameLink.enhance.url != null) {
                if(this.leadsEnhanceName.trim() == '' || this.leadsEnhanceUrl.trim() == '') {
                    this.$notify({
                        type: 'primary',
                        message: 'All fields are mandatory.',
                        icon: 'fas fa-bug'
                    });  
                    return false 
                }
            }
            
            if(this.$global.globalModulNameLink.b2b.name != null && this.$global.globalModulNameLink.b2b.url != null) {
                if(this.leadsB2bName.trim() == '' || this.leadsB2bUrl.trim() == '') {
                    this.$notify({
                        type: 'primary',
                        message: 'All fields are mandatory.',
                        icon: 'fas fa-bug'
                    });  
                    return false 
                }
            }

            if(this.$global.globalModulNameLink.simplifi.name != null && this.$global.globalModulNameLink.simplifi.url != null) {
                if(this.leadsSimplifiName.trim() == '' || this.leadsSimplifiUrl.trim() == '') {
                    this.$notify({
                        type: 'primary',
                        message: 'All fields are mandatory.',
                        icon: 'fas fa-bug'
                    });  
                    return false 
                }
            }

            return true
        },
        filterGlobalModulNameLink(obj, type) {
            if(type == 'enhance') {
                // Destructuring untuk mendapatkan semua entri
                const { enhance, ...rest } = obj;
                // console.log({
                //     enhance,
                //     rest
                // });
                // Mengembalikan objek tanpa entri enhance jika name dan url kosong
                return enhance.name === null && enhance.url === null ? rest : { ...rest, enhance };
            } else if(type == 'b2b') {
                // Destructuring untuk mendapatkan semua entri
                const { b2b, ...rest } = obj;
                // console.log({
                //     b2b,
                //     rest
                // });
                // Mengembalikan objek tanpa entri b2b jika name dan url kosong
                return b2b.name === null && b2b.url === null ? rest : { ...rest, b2b };
            } else if(type == 'simplifi') {
                // Destructuring untuk mendapatkan semua entri
                const { simplifi, ...rest } = obj;
                console.log({
                    simplifi,
                    rest
                });
                // Mengembalikan objek tanpa entri simplifi jika name dan url kosong
                return simplifi.name === null && simplifi.url === null ? rest : { ...rest, simplifi };
            }
        },
        save_general_custommenumodule() {
            // validate product url dan name
            if(!this.validationProductUrl()) return;
            if(!this.validateProductname()) return;
            // validate product url dan name

            this.isLoadingCostumeModule = true
            
            // sync global module name link
            this.syncGlobalModulNameLink()
            // sync global module name link

            // filter comset name
            var _comsetname = 'customsidebarleadmenu';
            if (this.$global.systemUser) {
                _comsetname = 'rootcustomsidebarleadmenu';
            }
            // filter comset name

            // filter global module name link for enhance and b2b and simplifi
            let globalModulNameLink = { ...this.$global.globalModulNameLink };
            if(this.$global.globalModulNameLink.enhance.name == null || this.$global.globalModulNameLink.enhance.url == null) {
                globalModulNameLink = this.filterGlobalModulNameLink(globalModulNameLink, 'enhance');
            }
            if(this.$global.globalModulNameLink.b2b.name == null || this.$global.globalModulNameLink.b2b.url == null) {
                globalModulNameLink = this.filterGlobalModulNameLink(globalModulNameLink, 'b2b');
            }
            if(this.$global.globalModulNameLink.simplifi.name == null || this.$global.globalModulNameLink.simplifi.url == null) {
                globalModulNameLink = this.filterGlobalModulNameLink(globalModulNameLink, 'simplifi');
            }
            // filter global module name link for enhance and b2b and simplifi
            
            this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'custommenumodule',
                comsetname: _comsetname,
                comsetval: globalModulNameLink,
            }).then(response => {
                //console.log(response.data.local.name);
                if (response.result == "success") {
                    
                    this.userData.leadlocalname = this.leadsLocalName;
                    this.userData.leadlocalurl = this.leadsLocalUrl;

                    this.userData.leadlocatorname = this.leadsLocatorName;
                    this.userData.leadlocatorurl = this.leadsLocatorUrl;
                    
                    this.userData.leadenhancename = this.leadsEnhanceName;
                    this.userData.leadenhanceurl = this.leadsEnhanceUrl;

                    this.userData.leadb2bname = this.leadsB2bName;
                    this.userData.leadb2burl = this.leadsB2bUrl;

                    this.userData.leadsimplifiname = this.leadsSimplifiName;
                    this.userData.leadsimplifiurl = this.leadsSimplifiUrl;

                    const updatedData = {
                        leadlocalname: this.leadsLocalName,
                        leadlocalurl: this.leadsLocalUrl,
                        leadlocatorname: this.leadsLocatorName,
                        leadlocatorurl: this.leadsLocatorUrl,
                        leadenhancename: this.leadsEnhanceName,
                        leadenhanceurl: this.leadsEnhanceUrl,
                        leadb2bname: this.leadsB2bName,
                        leadb2burl: this.leadsB2bUrl,
                        leadsimplifiname: this.leadsSimplifiName,
                        leadsimplifiurl: this.leadsSimplifiUrl,
                    }

                    this.$store.dispatch('updateUserData', updatedData);

                    this.$notify({
                        type: 'success',
                        message: 'Setting has been saved.',
                        icon: 'tim-icons icon-bell-55'
                    });  
                    //window.location.reload(true);
                    this.$router.go(0);
                }
                    this.isLoadingCostumeModule = false
            },error => {
                this.$notify({
                    type: 'danger',
                    message: error.response.data.message,
                    icon: 'fas fa-bug'
                });
                    this.isLoadingCostumeModule = false
            });
        },
        save_general_fontheme() {
            this.isLoadingFont = true
            this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'fonttheme',
                fonttheme: this.fontthemeactive,
            }).then(response => {
                //console.log(response[0]);  
                this.fonttheme =  this.fontthemeactive;
                
                this.userData.font_theme = this.fonttheme;

                const updatedData = {
                    font_theme: this.fonttheme
                }
                this.$store.dispatch('updateUserData', updatedData);

                this.$notify({
                    type: 'success',
                    message: 'Setting has been saved.',
                    icon: 'tim-icons icon-bell-55'
                });  
                 this.isLoadingFont = false
            },error => {
                        
                this.isLoadingFont = false
            });
        },
        save_general_colortheme() {
            this.isLoadingColorPalete = true
            this.$store.dispatch('updateGeneralSetting', {
                companyID: this.userData.company_id,
                actionType: 'colortheme',
                sidebarcolor: this.colors.sidebar,
                // templatecolor: $('#backgroundtemplatecolor').val(),
                // boxcolor: $('#boxcolor').val(),
                textcolor: this.colors.text,
                // linkcolor: $('#linkcolor').val(),
            }).then(response => {
                //console.log(response[0]);  
                this.sidebarcolor = this.colors.sidebar;
                // this.backgroundtemplatecolor = $('#backgroundtemplatecolor').val();
                // this.boxcolor = $('#boxcolor').val();
                this.textcolor = this.colors.text;
                // this.linkcolor = $('#linkcolor').val();
                
                this.userData.sidebar_bgcolor = this.colors.sidebar;
                // this.userData.template_bgcolor = this.backgroundtemplatecolor;
                // this.userData.box_bgcolor = this.boxcolor;
                this.userData.text_color = this.colors.text;
                // this.userData.link_color = this.linkcolor;

                const updatedData = {
                    sidebar_bgcolor: this.colors.sidebar,
                    text_color: this.colors.text
                }

                this.$store.dispatch('updateUserData', updatedData);

                this.$notify({
                    type: 'success',
                    message: 'Setting has been saved.',
                    icon: 'tim-icons icon-bell-55'
                });  
                    this.isLoadingColorPalete = false
            },error => {
                    this.isLoadingColorPalete = false
            });
        },
        check_whitelabelling() {
            if (this.userData.user_type == 'userdownline' || this.userData.user_type == 'user') {
                this.DownlineDomain = this.userData.domain;
                this.DownlineSubDomain = this.userData.subdomain;
                if (this.userData.whitelabelling == 'F') {
                    //this.Whitelabellingstatus = false;
                    this.chkagreewl = false;
                }else{
                    //this.Whitelabellingstatus = true;
                    this.chkagreewl = true;
                }
                if (this.userData.status_domain == 'action_retry') {
                    this.domainSetupCompleted = false;
                    this.DownlineDomainStatus = 'Please add A record to our IP server.';
                }else if (this.userData.status_domain == 'action_check_manually') {
                    this.domainSetupCompleted = false;
                    this.DownlineDomainStatus = 'Need manually configuration please contact <a href="mailto:support@' + this.$global.companyrootdomain + '">support</a>';
                }else if (this.userData.status_domain == 'ssl_acquired') {
                    this.domainSetupCompleted = true;
                    this.DownlineDomainStatus = 'Domain Setup Completed.';
                }else{
                    this.domainSetupCompleted = false;
                    this.DownlineDomainStatus = 'Domain not setup yet.';
                }
            }

        },
        showDomainRetryConfirmation() {
            // Open the confirmation modal
            this.modals.domainRetryConfirmation = true;
        },
        confirmDomainRetry() {
            // Validate domain input first
            if (!this.DownlineDomain || this.DownlineDomain.trim() === '') {
                this.$notify({
                    type: 'warning',
                    message: 'Please enter a domain name before attempting SSL reconfiguration.',
                    icon: 'tim-icons icon-bell-55'
                });
                return;
            }

            // Close the modal and disable retry button
            this.modals.domainRetryConfirmation = false;
            this.isRetryingDomainSSL = true;

            this.$store.dispatch('retryDomainSSL', {
                domain: this.DownlineDomain,
                companyID: this.userData.company_id
            }).then(response => {
                if (response.result == "success") {
                    this.$notify({
                        type: 'success',
                        message: 'Domain SSL reconfiguration initiated. This may take a few minutes. Please check your domain periodically to confirm the update has completed.',
                        icon: 'tim-icons icon-bell-55'
                    });
                    // Refresh the domain status
                    this.userData.status_domain = response.stdom;
                    this.$store.dispatch('updateUserData', this.userData);
                    this.check_whitelabelling();
                    this.isRetryingDomainSSL = false;
                }else{
                    this.$notify({
                        type: 'danger',
                        message: 'We couldn\'t verify that your domain\'s A record is pointing to 157.230.213.72. Please make sure your DNS settings include this IP and that there are no multiple A records configured for your domain.',
                        icon: 'tim-icons icon-bell-55'
                    });
                    this.isRetryingDomainSSL = false;
                }
            }).catch(error => {
                this.$notify({
                    type: 'danger',
                    message: 'Failed to reconfigure SSL certificate. Please try again.',
                    icon: 'tim-icons icon-bell-55'
                });
                this.isRetryingDomainSSL = false;
            });
        },
        handleDefaultModule(type, value){
            const module = this.defaultModule && this.defaultModule.find(mod => mod.type === type)
            if(module){
                module.status = value;
            }
        },
        saveDefaultModule(){
            const module = this.defaultModule.map(({ name, icon, ...rest }) => rest);
            this.isLoadingSaveDefaultModule = true
            const defaultModule = this.$global.systemUser ? 'rootdefaultmodules' : 'agencydefaultmodules';
            this.$store.dispatch('updateGeneralSetting', {
            companyID: this.userData.company_id,
            actionType: defaultModule,
            comsetval: module,
            }).then(response => {
                if (response.result == "success") {
                    this.$notify({
                        type: 'success',
                        message: 'Setting has been saved.',
                        icon: 'tim-icons icon-bell-55'
                    });
                    this.isLoadingSaveDefaultModule = false
                }
            },error => {
                this.$notify({
                        type: 'danger',
                        message: 'Something went wrong, please try again later',
                        icon: 'tim-icons icon-bell-55'
                });
                this.isLoadingSaveDefaultModule = false    
            });
        },
        cssDefaultModuleByLength(){
            const lengthDefaultModule = this.defaultModule.filter(module => module.name !== '' && module.name !== null);
            if(lengthDefaultModule.some(item => item.type === "b2b") && !this.validateBetaFeature('b2b_module')) {
                const index = lengthDefaultModule.findIndex(item => item.type === "b2b");
                if (index !== -1) {
                    lengthDefaultModule.splice(index, 1);
                }
            }

            if (lengthDefaultModule.length == 1){
                return 'col-sm-12 col-md-12 col-lg-12'
            } else if (lengthDefaultModule.length == 2){
                return 'col-sm-12 col-md-6 col-lg-6'
            } else if (lengthDefaultModule.length == 3){
                return 'col-sm-6 col-md-4 col-lg-4'
            } else if (lengthDefaultModule.length == 4){
                return 'col-sm-6 col-md-3 col-lg-3'
            } else {
                return 'col-sm-6 col-md-4 col-lg-4'
            }
        },
        validationProductUrl(){
            if(
                (this.leadsLocalUrl == this.leadsLocatorUrl) || 
                (this.leadsLocalUrl == this.leadsEnhanceUrl && this.leadsEnhanceUrl != null) ||
                (this.leadsLocalUrl == this.leadsB2bUrl && this.leadsB2bUrl != null) ||
                (this.leadsLocalUrl == this.leadsSimplifiUrl && this.leadsSimplifiUrl != null) ||

                (this.leadsLocatorUrl == this.leadsEnhanceUrl && this.leadsEnhanceUrl != null) ||
                (this.leadsLocatorUrl == this.leadsB2bUrl && this.leadsB2bUrl != null) ||
                (this.leadsLocatorUrl == this.leadsSimplifiUrl && this.leadsSimplifiUrl != null) ||

                (this.leadsEnhanceUrl == this.leadsB2bUrl && this.leadsEnhanceUrl != null && this.leadsB2bUrl != null) ||
                (this.leadsEnhanceUrl == this.leadsSimplifiUrl && this.leadsEnhanceUrl != null && this.leadsSimplifiUrl != null) ||
                
                (this.leadsB2bUrl == this.leadsSimplifiUrl && this.leadsB2bUrl != null && this.leadsSimplifiUrl != null)
            ){
                this.$notify({
                    type: 'danger',
                    message: 'The URL must be different. Please use a unique URL for each.',
                    icon: 'fas fa-bug'
                });

                return false
            }

            return true
        },
        handleFormatCurrency(type, field){
            const validInput = /^[0-9]*(\.[0-9]*)?$/;

            if(!validInput.test(this[field])){
                this[field] = 0
            }

            if(type == 'simplifi'){
                if(field == 'SimplifiMaxBid'){
                    this.validateMinimumInput('blur', 'maxBid')
                }else if(field == "SimplifiDailyBudget"){
                    this.validateMinimumInput('blur', 'dailyBudget')
                }
            }

            const formatNumber = formatCurrencyUSD(this[field])
            this[field] = formatNumber
            this.set_fee(type, field)
        },
        restrictInput(event) {
            const input = event.target.value;
            const char = event.key;

            if (['Backspace', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(char)) {
                return; 
            }

            if (!char.match(/[0-9]/) && char !== '.') {
                event.preventDefault();
            }

            // Allow only one period
            if (char === '.' && input.includes('.')) {
                event.preventDefault();
            }
        },
        onSidebarChange(value){
            if(value == null){
                value = '#fff'
            }

            this.colors.sidebar = value
            $('.sidebar').css('background', value);
            $('head').append('<style>.sidebar:before{border-bottom-color:' +  value + ' !important;}</style>');
            document.documentElement.style.setProperty('--bg-bar-color', value);
        },
        onTextColorChange(value){
            if(value == null){
                value = '#fff'
            }
            
            this.colors.text = value
            $('#cssGlobalTextColor').remove();
            $('head').append('<style id="cssGlobalTextColor">.sidebar-wrapper a span small, .sidebar-wrapper #sidebarCompanyName, .sidebar-menu-item p, .company-select-tag, .sidebar-normal {color:' + value + ' !important;}</style>');
            document.documentElement.style.setProperty('--text-bar-color', value);
        },
        onSidebarActiveChange(value){
            const color = this.rgbToHex(value)
            this.colors.sidebar = color
            $('.sidebar').css('background', color);
            $('head').append('<style>.sidebar:before{border-bottom-color:' +  color + ' !important;}</style>');
            document.documentElement.style.setProperty('--bg-bar-color', color);
        },
        onTextActiveChange(value){
            const color = this.rgbToHex(value)
            this.colors.text = color
            $('#cssGlobalTextColor').remove();
            $('head').append('<style id="cssGlobalTextColor">.sidebar-wrapper a span small, .sidebar-wrapper #sidebarCompanyName, .sidebar-menu-item p, .company-select-tag, .sidebar-normal {color:' + color + ' !important;}</style>');
            document.documentElement.style.setProperty('--text-bar-color', color);
        },
        rgbToHex(rgb){
            const rgbArray = rgb.match(/\d+/g);
            if (!rgbArray) return rgb;

            return `#${rgbArray
                .slice(0, 3)
                .map((x) => parseInt(x, 10).toString(16).padStart(2, "0"))
                .join("")}`;
        },
        showModalAppPassword(){
            this.modals.isAppPassword = true;
        },
        validateEmailHost(){
            const domainRegex = /^([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/;
            const host = this.customsmtp.host || '';
            return domainRegex.test(host.trim());
        },
        validatePort() {
            this.customsmtp.port = this.customsmtp.port.replace(/[^0-9]/g, '');
        }
    },
    computed: {
        checkFieldsEmailSettings() {
            const { host, port, username, password } = this.customsmtp;
            return (host && host.trim() !== '') || (port && port.trim() !== '') || (username && username.trim() !== '') || (password && password.trim() !== '');
        },
        filteredSubAccounts() {
            if (!this.searchSubAccountLeadConnector) {
                return this.subAccountLeadConnector;
            }
           const searchKeyword = this.searchSubAccountLeadConnector.toLowerCase().trim();
           return this.subAccountLeadConnector.filter(account => {
               return (
                    account.company.toLowerCase().includes(searchKeyword) || 
                    account.email.toLowerCase().includes(searchKeyword)
                );
            });
        },
    },
    watch: {
        checkFieldsEmailSettings(newValue) {
            this.customsmtp.default = !newValue;
        },
        'modals.mindSpendConfig': function(newValue) {
            if(!newValue) {
                this.closeHelpModalMinSpendModal();
            }
        },
        'modals.mindSpendConfigEdit': function(newValue) {
            if(!newValue) {
                this.closeHelpModalEditMinSpendModal();
            }
        },
        'modals.createSubAccountLeadConnector': function(newValue) {
            if(!newValue) {
                this.closeHelpModalCreateSubAccountLeadConnector();
            }
        }
    },
    mounted() {
        if (!this.$global.systemUser && this.$global.idsys == this.$global.masteridsys) {
            this.getMinimumValueSimplifi();
        }

        window.addEventListener('scroll', this.onScroll);
        window.addEventListener("message", this.handleFeedbackConnectGhlV2); // untuk menerima sinyal/feedback saat connect ghl, dan sistem kita di iframe
        this.userData = this.$store.getters.userData;
        this.trialEndDate = new Date(this.userData.trial_end_date);
        this.defaultPaymentMethod = this.userData.paymentgateway;
        this.hasPassedFreeTrial();
        this.plannextbill = 'free';

        if (!this.$global.systemUser) {
            this.embeddedCodeTitle = 'Client';
        }
        this.selectsPaymentTerm.PaymentTerm = this.$global.rootpaymentterm;

        this.sidebarcolor = this.userData.sidebar_bgcolor;
        this.backgroundtemplatecolor = this.userData.template_bgcolor;
        this.boxcolor = this.userData.box_bgcolor;
        this.textcolor = this.userData.text_color;
        this.linkcolor = this.userData.link_color;
        this.colors.sidebar = this.userData.sidebar_bgcolor;
        this.colors.text = this.userData.text_color;
        
        this.images.login = (this.userData.login_image != '')?this.userData.login_image:this.images.login;
        this.images.register = (this.userData.client_register_image != '')?this.userData.client_register_image:this.images.register;
        this.images.agency = (this.userData.agency_register_image != '')?this.userData.agency_register_image:this.images.agency;
        this.logo.loginAndRegister = (this.userData.logo_login_register == null || this.userData.logo_login_register == '') ? this.logo.loginAndRegister : this.userData.logo_login_register;

        // this.selectsPaymentTerm.PaymentTermSelect = (typeof(this.userData.paymentterm_default) != 'undefined' && this.userData.paymentterm_default != '')?this.userData.paymentterm_default:'Weekly';
        this.paymentTermStatus();

        const moduleAgency = this.$global.agencyDefaultModules        
        const agencyFilterModules = this.$global.agencyfilteredmodules
        // console.log({'global.agencyDefaultModules.alias.moduleAgency': this.$global.agencyDefaultModules,'global.agencyfilteredmodules.alias.agencyFilterModules': this.$global.agencyfilteredmodules})

        const defaultModule = this.defaultModule.map(module => {
            const agencyModule = moduleAgency && moduleAgency.find(agency => agency.type == module.type)
            if(agencyModule){
                const status = agencyModule.status !== undefined ? agencyModule.status : true
                return  {...module, status: status}
            } else {
                return module
            }
        })
        // console.log({'defaultModule': defaultModule})

        if(agencyFilterModules){
            const local = agencyFilterModules.local !== undefined ? agencyFilterModules.local.name : ''
            const locator = agencyFilterModules.locator !== undefined ? agencyFilterModules.locator.name : ''
            const enhance = agencyFilterModules.enhance !== undefined ? agencyFilterModules.enhance.name : ''
            const b2b = agencyFilterModules.b2b !== undefined ? agencyFilterModules.b2b.name : '';
            const simplifi = agencyFilterModules.simplifi !== undefined ? agencyFilterModules.simplifi.name : ''; 
            
            defaultModule[0].name = local
            defaultModule[1].name = locator
            defaultModule[2].name = enhance
            defaultModule[3].name = b2b
            defaultModule[4].name = simplifi
        }


        this.defaultModule = defaultModule

        if(this.defaultModule[0].name){
            this.activePriceSettingTab = 1
        } else if (this.defaultModule[1].name){
            this.activePriceSettingTab = 2
        } else if (this.defaultModule[2].name){
            this.activePriceSettingTab = 3
        } else if (this.defaultModule[3].name) {
            this.activePriceSettingTab = 4;
        }  else if (this.defaultModule[4].name) {
            this.activePriceSettingTab = 5;
        } else {
            this.activePriceSettingTab = 1
        }

        // $('#sidebarcolor').val(this.userData.sidebar_bgcolor);
        $('#backgroundtemplatecolor').val(this.userData.template_bgcolor);
        $('#boxcolor').val(this.userData.box_bgcolor);
        // $('#textcolor').val(this.userData.text_color);
        $('#linkcolor').val(this.userData.link_color);

        if (this.userData.font_theme != '') {
            $('#' + this.userData.font_theme).addClass('fontactive');
        }else{
            $('#' + this.fonttheme).addClass('fontactive');
        }

        // Basic instantiation:
        // $('#sidebarcolor').colorpicker({
        //     format: 'hex',
        // });

        $('#backgroundtemplatecolor').colorpicker({
            format: 'hex',
        });

        $('#boxcolor').colorpicker({
            format: 'hex',
        });

        // $('#textcolor').colorpicker({
        //     format: 'hex',
        // });

        $('#linkcolor').colorpicker({
            format: 'hex',
        });
        

        // $('#sidebarcolor').on('colorpickerChange', function(event) {
        //     $('.sidebar').css('background', event.color.toString());
        //     $('head').append('<style>.sidebar:before{border-bottom-color:' +  event.color.toString() + ' !important;}</style>');
        //     document.documentElement.style.setProperty('--bg-bar-color', event.color.toString());

        // });

        $('#backgroundtemplatecolor').on('colorpickerChange', function(event) {
            $('.main-panel').css('background', event.color.toString());
            
        });

         $('#boxcolor').on('colorpickerChange', function(event) {
            $('.card').css('background', event.color.toString());
             $('.card-body').css('background',event.color.toString());
        });

        // $('#textcolor').on('colorpickerChange', function(event) {
        //     $('#cssGlobalTextColor').remove();
        //     $('head').append('<style id="cssGlobalTextColor">.sidebar-wrapper a span small, .sidebar-wrapper #sidebarCompanyName, .sidebar-menu-item p, .company-select-tag, .sidebar-normal {color:' + event.color.toString() + ' !important;}</style>');
        //     document.documentElement.style.setProperty('--text-bar-color', event.color.toString());
        // });

        $('#linkcolor').on('colorpickerChange', function(event) {
            $('#cssGlobalLinkColor').remove();
            $('head').append('<style id="cssGlobalLinkColor">body a, a span {color:' +  event.color.toString() + ' !important;}</style>');
        });
        
        /** PREPARE FOR UPLOAD RESUMABLE FILE */

        /** LOGO LOGIN AND REGISTER UPDATE */
        this.ruLogoLoginAndRegister = new Resumable({
            target: this.apiurl + '/file/upload',
            query:{
            newfilenameid:'_logologinandregister_',
            pkid:this.userData.company_id,
            uploadFolder:'users/images',
            } ,// CSRF token
            fileType: ['jpeg','jpg','png','gif'],
            headers: {
                'Accept' : 'application/json',
                'Authorization' : 'Bearer ' + localStorage.getItem('access_token'),
            },
            testChunks: false,
            throttleProgressCallbacks: 1,
            maxFileSize:1105920,
            maxFiles: 1,
            simultaneousUploads: 1,
            fileTypeErrorCallback: (file, errorCount) => {
                this.$notify({
                    type: 'danger',
                    icon: 'tim-icons icon-bell-55',
                    message: 'Format file is not valid!'
                });
            },
            maxFileSizeErrorCallback:function(file, errorCount) {
            filetolarge('3',file,errorCount,'1105920');
            },
        });

        this.ruLogoLoginAndRegister.assignBrowse(this.$refs.browseFileLogoLoginAndRegister);
        
        this.ruLogoLoginAndRegister.on('fileAdded', (file, event) => { // trigger when file picked
            $('#progressmsgshow3 #progressmsg label').html('* Please wait while your image uploads. (It might take a couple of minutes.)');
            this.showProgress('3');
            this.ruLogoLoginAndRegister.upload() // to actually start uploading.
            
        });

        this.ruLogoLoginAndRegister.on('fileProgress', (file) => { // trigger when file progress update
            this.updateProgress('3',Math.floor(file.progress() * 100));
        });

        this.ruLogoLoginAndRegister.on('fileSuccess', (file, event) => { // trigger when file upload complete
            const response = JSON.parse(event);
            //console.log(response.path);
            this.logo.loginAndRegister = response.path;
            this.userData.logo_login_register = response.path;
            
            const updatedData = {
                logo_login_register: response.path
            }

            this.$store.dispatch('updateUserData', updatedData);

            this.hideProgress('3');
        });

        this.ruLogoLoginAndRegister.on('fileError', (file, event) => { // trigger when there is any error
            console.log('file uploading failed contact admin.');
        });
        

        this.hideProgress('3');
        /** LOGO LOGIN AND REGISTER UPDATE */

        /** LOGO SIDEBAR UPDATE */
        this.ruLogoSidebar = new Resumable({
            target: this.apiurl + '/file/upload',
            query:{
            newfilenameid:'_logophoto_',
            pkid:this.userData.company_id,
            uploadFolder:'users/images',
            } ,// CSRF token
            fileType: ['jpeg','jpg','png','gif'],
            headers: {
                'Accept' : 'application/json',
                'Authorization' : 'Bearer ' + localStorage.getItem('access_token'),
            },
            testChunks: false,
            throttleProgressCallbacks: 1,
            maxFileSize:1105920,
            maxFiles: 1,
            simultaneousUploads: 1,
            fileTypeErrorCallback: (file, errorCount) => {
                this.$notify({
                    type: 'danger',
                    icon: 'tim-icons icon-bell-55',
                    message: 'Format file is not valid!'
                });
            },
            maxFileSizeErrorCallback:function(file, errorCount) {
            filetolarge('4',file,errorCount,'1105920');
            },
        });

        this.ruLogoSidebar.assignBrowse(this.$refs.browseFileLogoSidebar);
        
        this.ruLogoSidebar.on('fileAdded', (file, event) => { // trigger when file picked
            $('#progressmsgshow4 #progressmsg label').html('* Please wait while your image uploads. (It might take a couple of minutes.)');
            this.showProgress('4');
            this.ruLogoSidebar.upload() // to actually start uploading.
            
        });

        this.ruLogoSidebar.on('fileProgress', (file) => { // trigger when file progress update
            this.updateProgress('4',Math.floor(file.progress() * 100));
        });

        this.ruLogoSidebar.on('fileSuccess', (file, event) => { // trigger when file upload complete
            const response = JSON.parse(event);
            //console.log(response.path);
            this.logo.sidebar = response.path;
            this.userData.company_logo = response.path;
            document.getElementById('companylogosidebar').src = response.path;
            
            const updatedData = {
                company_logo: response.path
            }

            this.$store.dispatch('updateUserData', updatedData);

            this.hideProgress('4');
        });

        this.ruLogoSidebar.on('fileError', (file, event) => { // trigger when there is any error
            console.log('file uploading failed contact admin.');
        });
        

        this.hideProgress('4');
        /** LOGO SIDEBAR UPDATE */

        /** LOGIN IMAGE UPDATE */
        this.ru = new Resumable({
            target: this.apiurl + '/file/upload',
            query:{
              newfilenameid:'_loginphoto_',
              pkid:this.userData.company_id,
              uploadFolder:'users/images',
            } ,// CSRF token
            fileType: ['jpeg','jpg','png','gif'],
            headers: {
                'Accept' : 'application/json',
                'Authorization' : 'Bearer ' + localStorage.getItem('access_token'),
            },
            testChunks: false,
            throttleProgressCallbacks: 1,
            maxFileSize:1105920,
            maxFiles: 1,
            simultaneousUploads: 1,
            fileTypeErrorCallback: (file, errorCount) => {
                this.$notify({
                    type: 'danger',
                    icon: 'tim-icons icon-bell-55',
                    message: 'Format file is not valid!'
                });
            },
            maxFileSizeErrorCallback:function(file, errorCount) {
              filetolarge('',file,errorCount,'1105920');
            },
        });

        this.ru.assignBrowse(this.$refs.browseFileLogin);
        
        this.ru.on('fileAdded', (file, event) => { // trigger when file picked
            $('#progressmsgshow #progressmsg label').html('* Please wait while your image uploads. (It might take a couple of minutes.)');
            this.showProgress('');
            this.ru.upload() // to actually start uploading.
            
        });

        this.ru.on('fileProgress', (file) => { // trigger when file progress update
            this.updateProgress('',Math.floor(file.progress() * 100));
        });

        this.ru.on('fileSuccess', (file, event) => { // trigger when file upload complete
            const response = JSON.parse(event);
            //console.log(response.path);
            this.images.login = response.path;
            this.userData.login_image = response.path;

            const updatedData = {
                login_image: response.path
            }

            this.$store.dispatch('updateUserData', updatedData);

            this.hideProgress('');
        });

        this.ru.on('fileError', (file, event) => { // trigger when there is any error
            console.log('file uploading failed contact admin.');
        });
  
        this.hideProgress('');
        /** LOGIN IMAGE UPDATE */

        /** REGISTER IMAGE UPDATE */
        if (!this.$global.systemUser) {
            this.ru1 = new Resumable({
                target: this.apiurl + '/file/upload',
                query:{
                newfilenameid:'_registerphoto_',
                pkid:this.userData.company_id,
                uploadFolder:'users/images',
                } ,// CSRF token
                fileType: ['jpeg','jpg','png','gif'],
                headers: {
                    'Accept' : 'application/json',
                    'Authorization' : 'Bearer ' + localStorage.getItem('access_token'),
                },
                testChunks: false,
                throttleProgressCallbacks: 1,
                maxFileSize:1105920,
                maxFiles: 1,
                simultaneousUploads: 1,
                fileTypeErrorCallback: (file, errorCount) => {
                    this.$notify({
                        type: 'danger',
                        icon: 'tim-icons icon-bell-55',
                        message: 'Format file is not valid!'
                    });
                 },
                maxFileSizeErrorCallback:function(file, errorCount) {
                filetolarge('1',file,errorCount,'1105920');
                },
            });

            this.ru1.assignBrowse(this.$refs.browseFileRegister);
            
            this.ru1.on('fileAdded', (file, event) => { // trigger when file picked
                $('#progressmsgshow1 #progressmsg label').html('* Please wait while your image uploads. (It might take a couple of minutes.)');
                this.showProgress('1');
                this.ru1.upload() // to actually start uploading.
                
            });

            this.ru1.on('fileProgress', (file) => { // trigger when file progress update
                this.updateProgress('1',Math.floor(file.progress() * 100));
            });

            this.ru1.on('fileSuccess', (file, event) => { // trigger when file upload complete
                const response = JSON.parse(event);
                this.images.register = response.path;
                this.userData.client_register_image = response.path;

                const updatedData = {
                    client_register_image: response.path
                }

                this.$store.dispatch('updateUserData', updatedData);

                this.hideProgress('1');
            });

            this.ru1.on('fileError', (file, event) => { // trigger when there is any error
                console.log('file uploading failed contact admin.');
            });
    
            this.hideProgress('1');
        }
        /** REGISTER IMAGE UPDATE */

        /** AGENCY IMAGE UPDATE */
        if (this.$global.systemUser) {
            this.ru2 = new Resumable({
                target: this.apiurl + '/file/upload',
                query:{
                newfilenameid:'_agencyphoto_',
                pkid:this.userData.company_id,
                uploadFolder:'users/images',
                } ,// CSRF token
                fileType: ['jpeg','jpg','png','gif'],
                headers: {
                    'Accept' : 'application/json',
                    'Authorization' : 'Bearer ' + localStorage.getItem('access_token'),
                },
                testChunks: false,
                throttleProgressCallbacks: 1,
                maxFileSize:1105920,
                maxFiles: 1,
                simultaneousUploads: 1,
                fileTypeErrorCallback: (file, errorCount) => {
                    this.$notify({
                        type: 'danger',
                        icon: 'tim-icons icon-bell-55',
                        message: 'Format file is not valid!'
                    });
                },
                maxFileSizeErrorCallback:function(file, errorCount) {
                filetolarge('2',file,errorCount,'1105920');
                },
            });

            this.ru2.assignBrowse(this.$refs.browseFileAgency);
            
            this.ru2.on('fileAdded', (file, event) => { // trigger when file picked
                $('#progressmsgshow2 #progressmsg label').html('* Please wait while your image uploads. (It might take a couple of minutes.)');
                this.showProgress('2');
                this.ru2.upload() // to actually start uploading.
                
            });

            this.ru2.on('fileProgress', (file) => { // trigger when file progress update
                this.updateProgress('2',Math.floor(file.progress() * 100));
            });

            this.ru2.on('fileSuccess', (file, event) => { // trigger when file upload complete
                const response = JSON.parse(event);
                //console.log(response.path);
                this.images.agency = response.path;
                this.userData.agency_register_image = response.path;
                const updatedData = {
                    agency_register_image: response.path
                }

                this.$store.dispatch('updateUserData', updatedData);

                this.hideProgress('2');
            });

            this.ru2.on('fileError', (file, event) => { // trigger when there is any error
                console.log('file uploading failed contact admin.');
            });
            
    
            this.hideProgress('2');
        }
        /** AGENCY IMAGE UPDATE */

        /** PREPARE FOR UPLOAD RESUMABLE FILE */

        /** CHECK GOOGLE CONNECT */
        this.checkGoogleConnect();
        /** CHECK GOOGLE CONNECT */

        /** GET EMAIL CONFIGURATION */
        this.get_smtp_setting();
        /** GET EMAIL CONFIGURATION */

        /** GET AGENCY EMBEDDED CODE */
        this.get_agency_embeddedcode();
        /** GET EMAIL CONFIGURATION */

        /** FOR REFRESH URL CONNECTED STRIPE*/
        if (process.env.VUE_APP_DEVMODE == 'true') {
            // this.refreshURL = 'http://' + window.location.hostname + ':8080' +  this.refreshURL;
            // this.returnURL = 'http://' + window.location.hostname + ':8080' + this.returnURL;
            this.refreshURL = 'https://' + window.location.hostname +  this.refreshURL;
            this.returnURL = 'https://' + window.location.hostname + this.returnURL;
        }else{
            this.refreshURL = 'https://' + window.location.hostname  +  this.refreshURL;
            this.returnURL = 'https://' + window.location.hostname + this.returnURL;
        }
        /** FOR REFRESH URL CONNECTED STRIPE*/

        /** CHECK CONNECTED ACCOUNT */
        if (!this.$global.systemUser) {
            this.checkConnectedAccount();
        }
        /** CHECK CONNECTED ACCOUNT */

        /** FOR INITIAL PACKAGE PLAN */
        if (!this.$global.systemUser) {
            this.getAgencyPlanPrice();
        }
        /** FOR INITIAL PACKAGE PLAN */

        /** FOR WHITE LABELLING */
        if (!this.$global.systemUser) {
            this.check_whitelabelling();
        }
        /** FOR WHITE LABELLING */

        /** INITIALLY DEFAULT PRICE */
        //if (!this.$global.systemUser) {
            this.initial_default_price();
        //}
        /** INITIALLY DEFAULT PRICE */

        /* CHECK GOHIGHLEVEL CONNECT */
        if (!this.$global.systemUser && this.$global.idsys == this.$global.masteridsys) {
            this.checkGohighlevelConnect()
        }
        /* CHECK GOHIGHLEVEL CONNECT */
        
        /* GET LIST MINIMUM SPEND */
        if (this.$global.systemUser && this.$global.idsys == this.$global.masteridsys) {
            this.getMinimumSpendList();
        }
        /* GET LIST MINIMUM SPEND */
        
        /* LOAD ENABLED CLIENT DELETED ACCOUNT FROM AGENCY OWNER */
        if (!this.$global.systemUser && this.$global.enabled_client_deleted_account) {
            this.enabledDeletedAccountClient = ((this.$global.enabled_client_deleted_account === 'T') || false);
        }
        /* LOAD ENABLED CLIENT DELETED ACCOUNT FROM AGENCY OWNER */
    },
    beforeDestroy() {
        window.removeEventListener("message", this.handleFeedbackConnectGhlV2); // untuk menerima sinyal/feedback saat connect ghl, dan sistem kita di iframe
    }
};


function formatSize(size){
      if(size<1024) {
        return size + ' bytes';
      } else if(size<1024*1024) {
        return (size/1024.0).toFixed(0) + ' KB';
      } else if(size<1024*1024*1024) {
        return (size/1024.0/1024.0).toFixed(1) + ' MB';
      } else {
        return (size/1024.0/1024.0/1024.0).toFixed(1) + ' GB';
      }
}

function filetolarge(index,file,errorCount,filesize) {
      $('#progressmsgshow' + index + ' #progressmsg label').html(file.fileName||file.name +' is too large, please upload files less than ' + formatSize(filesize) + '.');
      $('#progressmsgshow' + index).show();
}

</script>
<style scoped>
.icon-default-minspend {
    /* background-color: green; */
    background-color: #409EFF;
    color: white;
    padding: 4px;
    width: 90px;
    text-align: center;
    position: absolute;
    right: 0;
    top: 0; 
    font-size: small;
}
.wholesale-cost-contact-title {
    text-align: center; 
    margin-bottom: 10px; 
    font-size: 16px;
}
.wholesale-cost-contact-list {
    display: flex; 
    flex-direction: row; 
    justify-content: start; 
    align-items: center; 
    margin-bottom: -8px;
}
.wholesale-cost-contact-message {
    text-align: start; 
    margin-left: 5px; 
    font-size: 14px;
}
</style>
<style>
.link-create-sub-acount-lead-connector {
    font-size: 0.80143rem;
    letter-spacing: -0.2px;
}
.disabled-link-create-sub-acount-lead-connector {
    pointer-events: none;
    cursor: not-allowed;
}

.card-list-subaccount-leadconnector {
    background-color: white;
    border: 1px solid #cad1d7;
    cursor: pointer;
}
.card-list-subaccount-leadconnector .card-body {
    padding: 0px !important;
}
.card-list-subaccount-leadconnector-cursor-default {
    cursor: default;
}
.child-card-list-subaccount-leadconnector {
    padding: 16px 16px 12px 16px;
}
.search-subleadconnector .el-input__inner {
	padding-left: 22px !important;
	padding-right: 22px !important;
}
.error-message-price-simplifi {
  color:#942434;
  font-size: 12px;
  font-weight: 400;
  line-height: 20px;
  margin-top: 4px;
  display: flex;
  word-wrap: break-word; 
  white-space: normal; 
  text-align: left;
}
.cpm-remove-border {
    border-left: none !important;
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
}
.btn-del-minspend {
    height: max-content !important;
    margin: 0 !important;
}

.message-general-ghl.el-message-box {
    max-width: 500px !important;
} 
.message-general-ghl .el-message-box__content {
    padding: 15px 15px 10px 15px !important;
}
.message-general-ghl .el-message-box__input {
    padding-top: 5px !important;
}
.message-general-ghl .el-message-box__header {
    padding-left: 16px !important;
}
.message-general-ghl .el-message-box__container {
    margin-left: 1px !important;
}

.radio-inactive {
    opacity: 0.5;
}

.radio-inactive:hover {
    opacity: 0.8;
}

.fontoption.fontactive {
    border: 1px solid  var(--info) !important;
}
.fontoption {
    border: 1px solid  var(--gray-dark) !important;
}

.modal .form-control {
    color: #222a42;
}

.email-template-item {
    padding-bottom: 5px;
    font-weight: 300;
    font-size: 0.9rem;
}
.pricing-setting-item-toggle{
    cursor: pointer;
    flex: 1;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}
.pricing-setting-item-toggle h5{
    margin:0;
}
.pricing-setting-item-toggle.--active{
    border: 1px solid var(--input-border-color);
}
.leadspeek-pricing-setting-form-wrapper{
    display:flex;
    align-items:baseline;
    gap:16px;
}
.price-setting-form-item .form-group{
    width: 100% !important;
    text-align:left;
}
.pricing-duration-dropdown-wrapper{
    display: flex;
    text-align: left;
    flex-direction: column;
    margin-bottom: 24px;
}

.product__default__module {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--muted);
    border-radius: 8px;
    padding: 16px;
    transition: border 0.3s ease;
}

.default__module__text {
    opacity: 0.5;
    transition: opacity 0.3s ease;
}

.active__default__module__text {
    font-weight: 600;
    opacity: 1;
}

.active__default__module {
    border: 2px solid var(--input-border-color);
}

.color_minimum_spend_config {
    color: var(--input-border-color);
}

.general-setting-side-nav .--active{
font-weight: bold;
}
.general-setting-side-nav .nav-link{
    cursor: pointer;
    transition: all 0.3s ease;
}
.general-setting-side-nav .nav-link:hover{
    font-weight: bold;
    color: var(--primary-color);
}

.default__theme .el-timeline-item__node {
    background-color: var(--text-primary-color);
}

.default__theme .el-timeline-item__timestamp {
    color: var(--text-primary-color) !important;
}

.tooltip-content {
  max-width: 300px;
  white-space: normal !important;
  word-break: break-word;
}

.default-price-helper-text{
    color:#999 !important;
    font-size:12px;
    font-weight: 400;
    line-height: 12px;
    margin-top: 4px;
    display: block;
    text-align: left;
}

/* .modal-subleadconnector .modal-content .modal-body  {
    background-color: white !important;
    color: black !important;
} */


.el-input__inner .search-subleadconnector {
    background-color: white;
    color: black;
}

.search-subleadconnector .el-input__inner {
    background-color: white !important; 
    color: black !important; 
    border-color: #555 !important;
}

.search-subleadconnector .el-input__inner::placeholder {
    color: #999 !important;
}

.search-subleadconnector .el-input__icon {
    color: white !important;
}


</style>