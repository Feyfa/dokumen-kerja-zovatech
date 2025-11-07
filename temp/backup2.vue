<template>
    <div style="width: 100%;" class="mt-2 mb-4">
        <card style="margin-bottom: 0px;">
            <div class="mb-3">
                <p>Name or Description</p>
                <base-input
                    :maxlength="254"
                    placeholder="Enter label (e.g. MD5 March)"
                    v-model="nameMD5" style="width: 100%; margin-bottom: 0px !important;">
                </base-input>
            </div>
            <div>
                <p>MD5 Hash / Email</p>
                <TextArea
                    v-model="md5Input"
                    placeholder="Enter MD5 hashes / Email, one per line"
                    :rows="6">
                </TextArea>
                <div class="d-flex flex-column mt-2">
                    <small>*You can submit a maximum of 100 MD5 entries.</small>
                    <small class="mt-1">*Your base price for cost per contact is ${{ costCleanId }}</small>
                </div>
            </div>
            <base-button class="mt-3" :disabled="(!md5Input && md5Input.trim() == '') || isFetchSubmitCleanID || !$global.agencyAgreeDataWallet" style="height: 40px; width: 100%;" @click="handleSubmit">
                Submit
                <i v-if="isFetchSubmitCleanID" class="fas fa-spinner fa-spin ml-2" style="font-size: 16px;"></i>
            </base-button>
        </card>
    </div>
</template>