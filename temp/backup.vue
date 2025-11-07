<template>
    <div v-if="active === 'inputManual'" style="width: 100%;" class="mt-3">
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
                <p>MD5 Hash</p>
                <TextArea
                    v-model="md5Input"
                    placeholder="Enter MD5 hashes, one per line"
                    :rows="6"
                    >
                </TextArea>
            </div>
            <base-button class="mt-3" :disabled="!md5Input && md5Input.trim() == ''" style="height: 40px; width: 100%;">Submit</base-button>
        </card>
    </div>
</template>