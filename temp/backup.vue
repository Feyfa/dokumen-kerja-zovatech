<template>
  <div>
    sdsa
  </div>
</template>


<script>
export default {
  method: {
    createDeleteIframeGhlv2() {
      if(!this.ghlV2CreatedIframe) { // create iframe
        this.createIframeGhlv2Client();
      } else { // delete iframe
        this.deleteIframeGhlv2Client();
      }
    },
    
    createIframeGhlv2Client() {
      if(!this.selects.ghlv2SubAccount) {
        this.$notify({
          type: 'danger',
          message: 'Please select sub accounts',
          icon: 'fas fa-bug'
        });
        return;
      }

      MessageBox.prompt('Enter the text that you want to display as your single sign on custom menu link. (30 Characters Max)', 'Enter Your Custom Menu Link Text.', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        inputPattern: /^$|^[a-zA-Z0-9\s]{1,30}$/,
        inputErrorMessage: 'Only letters, numbers, and spaces, max 30 characters',
        inputPlaceholder: '[Client-Name]',
        inputValue: this.$store.getters.userData.company_name,
        customClass: 'message-general-ghl'
      })
      .then(({ value }) => {
        this.isLoadingCreateDeleteIframeGhlV2 = true;
        this
        .$store
        .dispatch('createIframeGhlv2Client', {
          company_id: this.$store.getters.userData.company_id,
          company_parent: this.$store.getters.userData.company_parent,
          custom_menu_name: value,
          user_ip: this.$store.getters.userData.ip_login,
          location_id: this.selects.ghlv2SubAccount,
        })
        .then(response => {
          // console.log(response);
          this.isLoadingCreateDeleteIframeGhlV2 = false;
          this.ghlV2CreatedIframe = true;
          this.btnCreateDeleteIframeGhlv2Text = 'Remove SSO Link';
          
          this.ghlv2ResetListSubAccounts();
          this.ghlv2GetListSubAccounts();

          this.$notify({
            type: 'success',
            icon: 'fas fa-save',
            message: 'create sso link successfully'
          });
        })
        .catch(error => {
          console.error(error);
          this.isLoadingCreateDeleteIframeGhlV2 = false;
          const message = typeof(error.response.data.message) != 'undefined' ? error.response.data.message : 'Something Wrong';
          this.$notify({
            type: 'danger',
            icon: 'fas fa-bug',
            message: message
          });
        })
      });
    },

    deleteIframeGhlv2Client() {
      MessageBox.confirm('Are you sure you want to remove SSO link?', 'Warning', {
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel',
        type: 'warning',
      })
      .then(result => {
        this.isLoadingCreateDeleteIframeGhlV2 = true;
        this
        .$store
        .dispatch('deleteIframeGhlv2Client', {
          company_id: this.$store.getters.userData.company_id,
          company_parent: this.$store.getters.userData.company_parent,
          user_ip: this.$store.getters.userData.ip_login
        })
        .then(response => {
          this.isLoadingCreateDeleteIframeGhlV2 = false;
          this.ghlV2CreatedIframe = false;
          this.btnCreateDeleteIframeGhlv2Text = 'Create SSO Link';
          
          this.ghlv2ResetListSubAccounts();
          this.ghlv2GetListSubAccounts();
          
          this.$notify({
            type: 'success',
            icon: 'fas fa-save',
            message: 'Custom Menu Link Successfully Removed'
          });
        })
        .catch(error => {
          this.isLoadingCreateDeleteIframeGhlV2 = false;
          const message = typeof(error.response.data.message) != 'undefined' ? error.response.data.message : 'Something Wrong';
          this.$notify({
            type: 'danger',
            icon: 'fas fa-bug',
            message: message
          });
        });
      })
    },
  }
}
</script>