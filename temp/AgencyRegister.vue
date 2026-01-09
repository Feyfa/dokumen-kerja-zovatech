<template>
<main v-if="isLoading">
  <div style="background-color: white !important; min-height: 100vh; display: flex; justify-content: center; align-items: center;">
    <i class="fas fa-spinner fa-spin" style="font-size: 40px;"></i>
  </div>
</main>
<main v-else>
  <div>
    <div style="background-color: white !important;">
      <div class="row no-gutters">
        <div class="col-lg-6 col-md-0 col-sm-0 bg-auth-left" :style="{backgroundImage: `url(${loginpict})`, backgroundRepeat: 'no-repeat', backgroundSize: 'cover', backgroundPosition: 'center', minHeight: '100vh'}">
            <!-- <img v-bind-5:src="loginpict" alt="login" class="login-card-img" v-if="loginpict != ''"> -->
        </div>

      <div class="col-lg-6 col-md-12 col-sm-12" style="min-height: 100vh; display: flex; align-items: center;">
        <div style="width: 100%; display: flex; justify-content: center; align-items: center;">
        <ValidationObserver v-slot="{ handleSubmit }">
          <form @submit.prevent="handleSubmit(register)" autocomplete="off">
            <card class="content-auth-right">
              <div class="row">
                    <div class="col-lg-12 col-md-12 col-sm-12 pb-0 pt-0 mt-0 mb-0" style="display: flex; justify-content: center;">
                      <div style="height: 120px;">
                        <img v-bind:src="parentCompanyInfo.logo_login_register" alt="" style="max-width: 100%;max-height: 100%;" v-if="parentCompanyInfo.logo_login_register != ''">
                      </div>
                    </div>
                    <div class="col-lg-12 col-md-12 col-sm-12" style="display: flex; justify-content: center; margin-bottom: 24px; margin-top: 24px;">
                      <p style="font-size: 20px; font-weight: bold;">Set Up Your Agency Account</p>
                    </div>
                  </div>
              <el-card class="box-card card-auth-mobile" style="padding-top: 20px;">
              <ValidationProvider
                name="Agency / Company Name"
                rules="required"
                v-slot="{ passed, failed, errors }"
                >
                <base-input
                  :maxlength="256"
                  required
                  autocomplete=nofill
                  v-model="companyname"
                  placeholder="Agency/Company Name*"
                  addon-left-icon="tim-icons icon-single-02"
                  type="text"
                  :error="errors[0]"
                  :class="[{ 'has-success': passed }, { 'has-danger': failed }]">
                </base-input>
             </ValidationProvider>

              <ValidationProvider
                name="Full Name"
                rules="required"
                v-slot="{ passed, failed, errors }"
                >
                <base-input
                  :maxlength="255"
                  required
                  autocomplete=nofill
                  v-model="name"
                  placeholder="Full Name*"
                  addon-left-icon="tim-icons icon-single-02"
                  type="text"
                  :error="errors[0]"
                  :class="[{ 'has-success': passed }, { 'has-danger': failed }]">
                </base-input>
             </ValidationProvider>


              <ValidationProvider
                name="Email"
                rules="required|email"
                v-slot="{ passed, failed, errors }"
                >
                <base-input
                  :maxlength="70"
                  max="70"
                  required
                  autocomplete=off
                  id="usrname"
                  :errorstatus= erroremail
                  v-on:focus="emailfocus"
                  v-model="email"
                  placeholder="Your Email*"
                  addon-left-icon="tim-icons icon-email-85"
                  type="email"
                  :lowercase="true"
                  :error="errors[0]"
                  :class="[{ 'has-success': passed }, { 'has-danger': failed }]">
                </base-input>
             </ValidationProvider>
            
             <ValidationProvider
                  name="Phone"
                  rules="required"
                  v-slot="{ passed, failed, errors }"
                >
                <base-input
                  required
                  id="phone"
                  v-model="phone"
                  placeholder="Phone Number*"
                  addon-left-icon="tim-icons icon-mobile"
                  type="text"
                  autocomplete="chrome-off"
                  :error="errors[0]"
                  :class="[{ 'has-success': passed }, { 'has-danger': failed }]">
                </base-input>
             </ValidationProvider>

             <ValidationProvider
             name="password"
             rules="required|min:6"
             v-slot="{ passed, failed, errors }"
             class="password-icon"
             >
             <base-input
                  :maxlength="254"
                  required
                  autocomplete=new-password
                  v-model="password"
                  placeholder="Password*"
                  addon-left-icon="tim-icons icon-lock-circle"
                  :type="showpasswordregister ? 'text' : 'password'"
                  :error="errors[0]"
                  :class="[{ 'has-success': passed }, { 'has-danger': failed }]">
                </base-input>
                  <button
                    type="button"
                    class="icon-password-login-register"
                    @click="showPasswordRegister"
                    >
                    <i :class="showpasswordregister ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
             </ValidationProvider>

             <ValidationProvider
                :id="referral"
                ref="refvalidator"
                name="referral code"
                :rules="{ uniqueReferral: { idsys: $global.idsys } }"
                v-slot="{ passed, failed, errors }"
                v-if="true"
                >
                <base-input
                  autocomplete=nofill
                  v-model="referral"
                  placeholder="Enter Referral Code ex. QYS123"
                  addon-left-icon="fas fa-user-tag"
                  type="text"
                  :readonly="refCodeReadOnly"
                  :error="errors[0]"
                  :class="[{ 'has-success': passed }, { 'has-danger': failed }]">
                </base-input>
             </ValidationProvider>
              <small>*Please empty the field if you do not have a referral code.</small>

              
              
              <base-checkbox :disabled="agreeTermDisable" v-model="chckAgreetc" class="text-left" :class="{'has-danger': chckAgreetcStat}">
                I have read and agree to the <a href="#termofuse" style="color:#919aa3;text-decoration:underline;font-weight:bold" :style="[chckAgreetcStat?{'color':'#ec250d'}:'']" v-on:click.stop.prevent="modals.termofuse = true">Terms of Use</a> and <a href="#privacypolicy" style="color:#919aa3;text-decoration:underline;font-weight:bold" :style="[chckAgreetcStat?{'color':'#ec250d'}:'']" v-on:click.stop.prevent="modals.privacypolicy = true">Privacy Policy</a>.
              </base-checkbox>

              <div class="col-lg-12 col-md-12 pl-0 pt-4">
                <!-- <div class="recaptchaContainer" ref="recaptchaContainer"></div> -->
                <grecaptcha
                  v-if="sitekey"
                  ref="recaptcha"
                  :sitekey="sitekey"
                  :callback="callback"
                  v-on:recaptchaID="getrecaptchaid1"
                ></grecaptcha>
                <small v-if="errorcaptcha"><span style="color:#ec250d">* Please prove you are human by click the recaptcha</span></small>
              </div>
              <div class="card-footer" style="padding-right:0px;padding-left:0px">
                  <button :disabled="isSubmitting"  type="submit" class="btn mb-3 btn-block btn-lg"> {{ btnRegText }} </button>
              </div>
              <p>Already Registered? <span><router-link to="/login" style="color:#919aa3;text-decoration:underline;font-weight:bold">Login Here</router-link></span></p>
              
              <small v-if="chckAgreetcStat" style="color:#ec250d">* Please check "I agree to the Terms of Use and Privacy Policy"</small>
            </el-card>
            </card>
          </form>
        </ValidationObserver>
      </div>
      </div>
      
    </div>
  </div>

           <!-- Processing Modal -->
           <modal :show.sync="modals.processing.showit" id="modalProcessing" headerClasses="justify-content-center" :showClose=false>
              <div v-if="modals.processing.onprogress">
                <h4 slot="header" class="title title-up text-center" style="color:#000">The system is creating your Account.</h4>
                <p class="text-center" style="font-size:18px">This may take up to one minute - please do not click on anything</p>
                <div class="text-center">
                  <img src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/creatingaccount.gif" border="0"/>
                </div>
              </div>
              <div v-if="modals.processing.finish">
                <h4 slot="header" class="title title-up">Initial Account Setup Completed!.</h4>
                <p class="text-center" style="font-size:18px">Congratulations! Your account has been created. Please login to continue setting up your account.</p>
                <div class="text-center">
                   <i class="fad fa-trophy-alt" style="font-size:9em"></i>
                </div>
              </div>
              <template slot="footer">
                <div class="container text-center pb-4">
                  <base-button v-if="modals.processing.finish"  @click="redirecttoapp">Click Here to Login</base-button>
                </div>
              </template>
            </modal>
           <!-- Processing Modal -->

           <!-- Term of Use Modal -->
            <modal :show.sync="modals.termofuse" headerClasses="justify-content-center" id="modaltermofuse">
              <div>
                <iframe src="/term-of-use" width="100%"  height="400vh"/>
              </div>
              <template slot="footer">
                <div class="container text-center pb-4">
                  <base-button  @click="understand_term">Yes, I Understand</base-button>
                </div>
              </template>
              <div class="container text-center pb-4">
                 <a href="/term-of-use/" target="_blank">Click here for Terms of use page</a>  
                </div>
            </modal>
            <!-- Term of Use Modal -->

            <!-- Privacy Policy Modal -->
            <modal :show.sync="modals.privacypolicy" headerClasses="justify-content-center" id="modalprivacypolicy">
              <div>
                <iframe src="/privacy-policy" width="100%"  height="400vh"/>
              </div>
              <template slot="footer">
                <div class="container text-center pb-4">
                  <base-button  @click="understand_policy();">Yes, I Understand</base-button>
                </div>
                <div class="container text-center pb-4">
                 <a href="/privacy-policy/" target="_blank">Click here for privacy policy page</a>  
                </div>
              </template>
            </modal>
            <!-- Term and Condition Modal -->

            <!-- Duplicate Email Modal -->
            <modal :show.sync="modals.duplicateemail" headerClasses="justify-content-center">
              <h4 slot="header" class="title title-up">Notification</h4>
              <p class="title-same-email">
                Sorry, This email address is already associated with an existing account. Please log in or use a different email address.
              </p>
              <template slot="footer">
                <div class="container text-center pb-4">
                  <base-button  v-on:click="dupemailclose">Ok</base-button>
                </div>
              </template>
            </modal>
            <!-- Duplicate Email Modal -->

  </div>
</main>
</template>
<script>

import { Modal } from 'src/components';
import { store } from 'src/store/store'
import { BaseCheckbox } from 'src/components';
import { ValidationProvider, extend } from "vee-validate";
import { required, email } from "vee-validate/dist/rules";
import grecaptcha from 'src/components/ReCaptcha.vue';
import swal from 'sweetalert2';
import { Card, Notification } from 'element-ui'

extend("email", email);
extend("required", required);

extend('uniqueReferral', {
  validate: async (value , args) => {
    try {
      const idsys = args.idsys; 
      const response = await store.dispatch('CheckRefLink', {
        refcode: value
      });

      // Check the response and return true or false accordingly
      if(response.company_parent === idsys) {
            return response.result === 'success';
      } else {
          throw Error('Invalid referral code')
      }
      
      
    } catch (error) {
      console.error('Error validating referral:', error);
      return false;
    }
  },
   params: ['idsys']
});

export default {
  components: {
    BaseCheckbox,
    Modal,
    grecaptcha,
    ValidationProvider,
    [Card.name]: Card,
  },
  data() {
    return {
        layoutready: false,
        email: '',
        name: '',
        phone: '',
        companyname:'',
        password: '',
        showpasswordregister : false,
        referral : '',
        btnRegText: 'Register',
        isSubmitting: false,
        chckAgreetc: false,
        chckAgreetcStat:false,
        agreeTermDisable:false,
        iunderstandterm:false,
        iunderstandprivacy:false,
        erroremail:false,
        errorcaptcha:false,
        sitekey: "",
        gtoken:null,
        recaptchaid1: '',
        recaptchaInstance:null,
        modals: {
          termofuse: false,
          privacypolicy: false,
          duplicateemail:false,
          processing: {
            showit:false,
            onprogress: true,
            finish: false,
            url:'#',
          }
        },
        loginpict: '',
        parentCompanyInfo: {
          ownedcompanyid:"",
          domain: "",
          subdomain: "",
          logo_login_register: "",
          externalorgid: "",
        },
        tmpUserData : {
          leadlocalname: '',
          leadlocalurl: '',
          leadlocatorname: '',
          leadlocatorurl: '',
        },

        refCodeReadOnly: false,
        isLoading: false,

    };
  },
  methods: {
    showPasswordRegister() {
      this.showpasswordregister = !this.showpasswordregister
    },
    getrecaptchaid1(recaptchaid) {
      this.recaptchaid1 = recaptchaid;
    },
    understand_term() {
      this.iunderstandterm = true;
      this.modals.termofuse = false;
      if (this.iunderstandprivacy == true && this.iunderstandterm == true) {
        this.agreeTermDisable = false;
      }
    },
    understand_policy() {
      this.iunderstandprivacy = true;
      this.modals.privacypolicy = false;
      if (this.iunderstandprivacy == true && this.iunderstandterm == true) {
        this.agreeTermDisable = false;
      }
    },
    callback(token) {
      this.gtoken = token
    },
    dupemailclose() {
      this.modals.duplicateemail = false;
      document.getElementById('usrname').focus();
      document.getElementById('usrname').select();
    },
    login_google() {
      this.chckAgreetcStat = false;
      if (this.chckAgreetc == false) {
        this.chckAgreetcStat = true;
        return false;
      }

      this.btnRegText = 'Processing...';
      this.isSubmitting = true;
      
      var left = (screen.width/2)-(1024/2);
	    var top = (screen.height/2)-(800/2);
      var fbwindow = window.open(process.env.VUE_APP_DATASERVER_URL + '/auth/google','googlelogin',"menubar=no,toolbar=no,status=no,width=640,height=800,toolbar=no,location=no,modal=1,left="+left+",top="+top);
    },
    redirecttoapp() {
      document.location = this.modals.processing['url'];
      return false;
    },
    register() {
      this.chckAgreetcStat = false;
      if (this.chckAgreetc == false) {
        this.chckAgreetcStat = true;
        return false;
      }
      
      this.btnRegText = 'Processing...';
      this.isSubmitting = true;

      this.modals.processing['showit'] = true;
      this.modals.processing['onprogress'] = true;
      this.modals.processing['finish'] = false;
      this.modals.processing['url'] = '#';
      $('#modalProcessing').css('pointer-events','none');
      
      this.$store.dispatch('register', {
        companyname: this.companyname,
        name: this.name,
        email: this.email,
        phonenum: this.phone,
        password: this.password,
        gtoken: this.gtoken,
        userType: 'userdownline',
        domainName: window.location.hostname,
        ownedcompanyid: this.parentCompanyInfo.ownedcompanyid,
        idsys: this.$global.idsys,
        refcode: this.referral,
        tfa_active: 1,
        tfa_type: 'email',
      })
        .then(response => {
          //console.log(response);
          if(response.result == 'success') {
            //this.$router.push({ name: 'Login' })
            /** DIRECT PUT ON LOGIN */
            this.modals.processing['onprogress'] = false;
            this.modals.processing['finish'] = true;
            this.modals.processing['url'] = response.url;
            /** DIRECT PUT ON LOGIN */
          }else{ 
            
            if (response.message == 'email exist') {
              this.erroremail = true;
              this.modals.duplicateemail = true;
              
              this.modals.processing['showit'] = false;
              this.modals.processing['onprogress'] = true;
              this.modals.processing['finish'] = false;
              this.modals.processing['url'] = '#';
              $('#modalProcessing').css('pointer-events','');
            }else if (response.message == 'Recaptcha Invalid') {
              this.errorcaptcha = true

              this.modals.processing['showit'] = false;
              this.modals.processing['onprogress'] = true;
              this.modals.processing['finish'] = false;
              this.modals.processing['url'] = '#';
              $('#modalProcessing').css('pointer-events','');
            }else{
              Notification.error({
                title: 'Error',
                message: response.message || "Something Went Wrong",
                customClass: 'error-notification'
              });

              this.modals.processing['showit'] = false;
              this.modals.processing['onprogress'] = true;
              this.modals.processing['finish'] = false;
              this.modals.processing['url'] = '#';
              $('#modalProcessing').css('pointer-events','');
            }

            this.btnRegText = 'Register';
            this.isSubmitting = false;
            this.errorcaptcha = false;
            // window.grecaptcha.reset(this.recaptchaInstance);
            this.$refs.recaptcha.reset();
          }
          
        }, error => {
          //console.log(error);
            this.btnRegText = 'Register';
            this.isSubmitting = false;
            this.errorcaptcha = false;
            //window.grecaptcha.reset(this.recaptchaInstance)
            Object.keys(error).forEach(key => {
              let errval = error[key][0]; // value of the current key
              //console.log(errval);
              if(errval == 'Recaptcha Invalid') {
                this.errorcaptcha = true
                this.modals.processing['showit'] = false;
                this.modals.processing['onprogress'] = true;
                this.modals.processing['finish'] = false;
                this.modals.processing['url'] = '#';
                $('#modalProcessing').css('pointer-events','');
              }else if(errval == 'The email has already been taken.') {
                this.erroremail = true;
                this.modals.duplicateemail = true;
                this.modals.processing['showit'] = false;
                this.modals.processing['onprogress'] = true;
                this.modals.processing['finish'] = false;
                this.modals.processing['url'] = '#';
                $('#modalProcessing').css('pointer-events','');
              }

            });

            this.$refs.recaptcha.reset();

           
            //console.error(error)
        })
    },
    emailfocus() {
      this.erroremail = false;
    },
    getUserData() {
        
        this.$store.dispatch('retrieveUserData',{})
        .then(response => {
          if(response == 'success') {

              const userData = this.$store.getters.userData;

             /** SET TO STORAGE FOR SIDEMENU */
                userData.leadlocalname = this.$global.globalModulNameLink.local.name;
                userData.leadlocalurl = this.$global.globalModulNameLink.local.url;

                userData.leadlocatorname = this.$global.globalModulNameLink.locator.name;
                userData.leadlocatorurl = this.$global.globalModulNameLink.locator.url;

                userData.idsys = this.$global.idsys;

                const updatedData = {
                  leadlocalname: this.$global.globalModulNameLink.local.name,
                  leadlocalurl: this.$global.globalModulNameLink.local.url,
                  leadlocatorname: this.$global.globalModulNameLink.locator.name,
                  leadlocatorurl: this.$global.globalModulNameLink.locator.url,
                  idsys: this.$global.idsys
                }

                this.$store.dispatch('updateUserData', updatedData);
             /** SET TO STORAGE FOR SIDEMENU */
             
             const usetupcompleted = this.$store.getters.userData.profile_setup_completed
             if (usetupcompleted == "F") {
                window.document.location = "/user/profile-setup";
             }else{
                window.document.location = "/" + this.$global.globalModulNameLink.local.url + '/dashboard';
             }
             
             //this.$router.go(0);
             
          }else{
           this.btnRegText = 'Register';
            this.isSubmitting = true;
            swal.fire({
              icon: 'error',
              title: 'Sorry!',
              text: 'There is currently an update processing on the platform. Please wait five minutes and try again.',
            })

          }

        })

    },

     startTokenSocial(e) {
      if (e.origin == process.env.VUE_APP_DATASERVER_URL) {
        this.putTokenSocial(e.data);
      }
    },

    putTokenSocial(acctkn) {
      this.$store.dispatch('putTokenSocial', {
        acctoken: acctkn,
      })
        .then(response => {
          if(response != 'loginfailed') {
            this.getUserData();
          }
      })
    },
    async checkdomainsubdomain() {
      this.isLoading = true
      var currurl = window.location.hostname
      const mainDomain = currurl.replace(/(.*?)\.(?=.*\.)/, '');

      await this.$store.dispatch('getDomainorSubInfo', {
          domainorsub: window.location.hostname,
        }).then(response => {
            $('#mainagencyregister').show();
            //console.log(response);
            $('link[rel="icon"]').attr('href', response.params.logo_login_register);
            $('link[rel="apple-touch-icon"]').attr('href', response.params.logo_login_register);
            $('meta[name="apple-mobile-web-app-title"]').attr('content',response.params.companyname);
            
            if (response.urlredirect.agencyregisterurl != "" && response.urlredirect.agencyregisterurl != "#") {
              document.location = response.urlredirect.agencyregisterurl;
              return false;
            }

            if (response.params.ownedcompanyid == "") {
              document.location = 'http://' + response.params.subdomain;
              return false;
            }
            if (response.params.idsys != response.params.ownedcompanyid) {
              this.$router.push({ name: 'Login' })
            }
            if (response.urlredirect.agencyregisterurl == "#") {
              document.location = "/login";
              return false;
            }
            this.parentCompanyInfo.ownedcompanyid = response.params.ownedcompanyid;
            this.parentCompanyInfo.domain = response.params.domain;
            this.parentCompanyInfosubdomain = response.params.subdomain;
            this.parentCompanyInfo.logo_login_register = response.params.logo_login_register;
            this.parentCompanyInfo.externalorgid  = response.params.externalorgid;
            this.loginpict = response.params.agency_register_image;
            this.$global.globalBoxBgColor = response.params.box_bgcolor;
            this.$global.globalTemplateBgColor = response.params.template_bgcolor;
            this.layoutready = true;
            this.$global.idsys = response.params.idsys;
            this.$global.recapkey = response.params.recapkey;
            this.sitekey = response.params.recapkey;
            document.title = response.params.companyname + " Agency Register";
            this.isLoading = false

            /** FOR CUSTOM SIDE MENU NAME */
              if (response.sidemenu != '') {
                this.$global.globalModulNameLink.local.name = response.sidemenu.local.name;
                this.$global.globalModulNameLink.local.url = response.sidemenu.local.url;

                this.$global.globalModulNameLink.locator.name = response.sidemenu.locator.name;
                this.$global.globalModulNameLink.locator.url = response.sidemenu.locator.url;

                this.$global.globalModulNameLink.enhance.name = response.sidemenu.enhance.name;
                this.$global.globalModulNameLink.enhance.url = response.sidemenu.enhance.url;
              }
            /** FOR CUSTOM SIDE MENU NAME */

            /** SET TO STORAGE FOR SIDEMENU */

              this.tmpUserData.leadlocalname = this.$global.globalModulNameLink.local.name;
              this.tmpUserData.leadlocalurl = this.$global.globalModulNameLink.local.url;

              this.tmpUserData.leadlocatorname = this.$global.globalModulNameLink.locator.name;
              this.tmpUserData.leadlocatorurl = this.$global.globalModulNameLink.locator.url;

              const updatedData = {
                  leadlocalname: this.$global.globalModulNameLink.local.name,
                  leadlocalurl: this.$global.globalModulNameLink.local.url,
                  leadlocatorname: this.$global.globalModulNameLink.locator.name,
                  leadlocatorurl: this.$global.globalModulNameLink.locator.url
              }

              this.$store.dispatch('updateUserData', updatedData);
            /** SET TO STORAGE FOR SIDEMENU */
            
        },error => {
            this.isLoading = false
            // this.parentCompanyInfo.logo_login_register = "/img/EMMLogo.png";
            // alert('Your domain or subdomain not register yet');
            document.location = 'https://' + mainDomain;
        })
    },
    checkReferralCode(_refcode){
      this.$store.dispatch('CheckRefLink', {
        refcode: _refcode,
      })
        .then(response => {
          if(response.result == 'success') {
            $('#' + _refcode + ' .form-group').removeClass('has-danger');
            $('#' + _refcode + ' .form-group .input-group-prepend').addClass('readonly');
            this.refCodeReadOnly = true;
          }else{
             $('#' + _refcode + ' .form-group').addClass('has-danger');
             $('#' + _refcode + ' .form-group .input-group-prepend').removeClass('readonly');
            this.refCodeReadOnly = false;
          }
      }, error => {
        $('#' + _refcode + ' .form-group').addClass('has-danger');
        $('#' + _refcode + ' .form-group .input-group-prepend').removeClass('readonly');
        this.refCodeReadOnly = false;
      });
       
    },
  },
  watch: {
    chckAgreetc: function(value) {
      if (value == true) {
        this.chckAgreetcStat = false;
      }
    },
    phone: function(newVal, oldVal){
      $('#phone').usPhoneFormat({
        format: 'xxx-xxx-xxxx',
      });
    }
  },
  mounted() {
      this.referral = "";
      if (this.$route.params.referralCode) {
        
        this.referral = this.$route.params.referralCode
        this.checkReferralCode(this.referral);
      }
      //let vimeoScript = document.createElement('script')
      //vimeoScript.setAttribute('src', 'https://player.vimeo.com/api/player.js')
      //document.head.appendChild(vimeoScript)
      //document.title = "Exact Match Marketing Registration";
      //this.referral = this.$route.query.refcode;
      this.checkdomainsubdomain();
      $('#phone').usPhoneFormat({
        format: 'xxx-xxx-xxxx',
      });
      if(window.location.hash == '#termofuse') {
        this.modals.termofuse = true;
      }else if (window.location.hash == '#privacypolicy') {
        this.modals.privacypolicy = true;
      }
      this.$store.dispatch('destroyToken')
      /** DISABLED RIGHT CLICK AND CONSOLE WARNING IF NOT GLOBAL VIEW */
   
      window.addEventListener('contextmenu', function (e) {
          e.preventDefault();
        });
        
        function consoleWarning() {
            console.log("%cWarning!", "color: red; font-size: 24px;");
            console.log("%cThis is a browser feature intended for developers. If someone told you to copy-paste something here to enable a feature or 'hack' someone's account, it is a scam and will give them access to your account.", "font-size: 16px;");
        }

        // Check if console is open
        var consoleOpened = false;
        setInterval(function() {
            if (!consoleOpened) {
                consoleOpened = true;
                consoleWarning();
            }
        }, 1000);
       /** DISABLED RIGHT CLICK AND CONSOLE WARNING IF NOT GLOBAL VIEW */
      window.addEventListener('message', this.startTokenSocial);
    },
};
</script>
<style>
.full-page > .content {
  padding-top: 0px !important;
}

.recaptchaContainer div {
  margin: 0 auto;
}
#modaltermofuse .termofuse, #modalprivacypolicy .termofuse {
  overflow-y: scroll;
  height: 300px;
  width: 100%;
  /*border: 1px solid #525f7f;*/
  padding: 10px;
}

#modaltermofuse ul li, #modalprivacypolicy ul li{
  color: #222a42;
}

.register-page .login-card {
    border: 0;
    border-radius: 27.5px;
    overflow: hidden;
}

.register-page .login-card-img {
  border-radius: 0;
    position: absolute;
    width: 100%;
    height: 100%;
    -o-object-fit: cover;
    object-fit: cover;
}

.register-page .login-card-body-padding {
    padding-left:40px;
    padding-right:40px;
    padding-top:0px;
}

.register-page .btn-danger,.register-page .btn-danger:hover {
  background-color: #942434 !important;
  background-image: linear-gradient(to bottom left, #942434, #ec250d, #fd5d93) !important;
}

.register-page .signtext {
  margin:0;
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  font-weight: bold;
  font-size: 13px;
}

.register-page .card {
  -webkit-box-shadow: none;
  box-shadow:none;
}

.register-page .content {
  height: 100vh;
  position: relative;
}

.register-page .box-register {
  position: absolute;
  width: 100%;
  top: 50%;
  transform: translate(-50%,-50%);
  left: 50%;
}

.register-page .login-card {
  margin-bottom: 0px !important;
}

.register-page .login-height-content{
  min-height: 720px;
}

.register-page .card .card-body {
  padding-top:30px;
}

.register-page .logo-card {
  min-height: 120px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.register-page .error {
  display: none;
}

.input-group-prepend.readonly {
  background-color: #1d253b !important;
  font-weight: bold !important;
}

.bg-auth-left {
  display: block;
}

.content-auth-right {
  padding: 16px !important;
  max-width: 500px;
}

.text-auth {
    margin-bottom: 0px !important;
}

.row-auth-mobile {
  margin-top: 0px;
}

.full-page .footer {
  display: none;
}

.title-same-email {
  margin: auto;
  text-align: center;
}

@media (max-width: 991px) {
  .bg-auth-left {
    display: none;
  }
  .text-auth {
    margin-bottom: 16px !important;
  }
}

@media (max-width: 767px) {
  .content-auth-right {
    padding: 16px !important;
  }
}

@media (max-width: 374px) {
    .card-auth-mobile {
      max-width: 315px;
    }
}
</style>
