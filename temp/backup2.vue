<template>
    <div v-if="selectedIntegration === 'googlesheet'" class="">
        <div v-if="isGoogleInfoFetching" class="row" v-loading="true" id="loading"></div>
        <div v-if="isLoadingResetSpreadsheet" class="row" v-loading="true" id="loading"></div>
        <div v-if="!isGoogleInfoFetching && !isLoadingResetSpreadsheet">
            <div>
                <base-input label="Client Emails Approved to View the Google Sheet:">
                    <textarea class="w-100" v-model="selectedRowData.report_sent_to" @keydown="handleGsheetKeydown" @paste="handleGSheetPaste"
                        placeholder="Enter email, separate by new line" rows="4">
                        </textarea>
                        <span>Seperate the emails by space or comma</span>
                    </base-input>
            </div>
            <div class="has-label form-group" :style="{display : userType === 'client' ? 'none' : 'block'}">
                <label>Admins Approved for Google Sheet Administration</label>
                <el-select multiple class="select-info select-fullwidth" size="large"
                    v-model="selectedRowData.admin_notify_to" placeholder="You can select multiple Admin">
                    <el-option v-for="option in selectsAdministrator.administratorList" class="select-info"
                        :value="option.id" :label="option.name" :key="option.id">
                    </el-option>
                </el-select>
            </div>
            <div class="mt-3">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <el-button
                    size="medium"
                    :icon="isLoadingSpreedSheetConnection ? 'el-icon-loading' : 'el-icon-s-promotion'"
                    @click="onSendTestSpreadsheet"
                    circle
                    :disabled="isLoadingSpreedSheetConnection"
                    ></el-button>
                    <el-button :disabled="isLoadingSpreedSheetConnection" type="text" @click="onSendTestSpreadsheet" style="margin-left: 0px;">Check Spreadsheet Connection</el-button>
                </div>
            </div>
            <div class="mt-3">
                <!-- MAIN SHEET -->
                <div v-if="selectedRowData.spreadsheet_id" class="gsheet-grid mt-1">
                    <a
                    class="gsheet-item"
                    :href="'https://docs.google.com/spreadsheets/d/' + selectedRowData.spreadsheet_id + '/edit#gid=0'"
                    target="_blank"
                    style="text-decoration: none; color: #222a42 !important;"
                    >
                        <span>Open google sheet</span>
                        <el-popover content="Go to Google Sheet" placement="top" trigger="hover" effect="light" :open-delay="300">
                        <i slot="reference" class="fab fa-google-drive  ml-2" style="color:#6699CC"></i>
                        </el-popover>
                    </a>
                </div>

                <label v-if="filteredHistory.length" class="mt-3">History Spreadsheet</label>
                <!-- HISTORY SHEET -->
                <div v-if="filteredHistory.length" class="mt-2">
                    <div class="history-grid">
                        <a v-for="(item, index) in filteredHistory" :key="index" class="history-item"
                        :href="'https://docs.google.com/spreadsheets/d/' + item.spreadsheet_id + '/edit#gid=0'" target="_blank"
                        style="text-decoration: none; color: #222a42 !important;">
                            <span class="history-label">
                                History {{ index + 1 }} ({{ item.created_at }})
                            </span>
                            <el-popover content="View this spreadsheet" placement="top" trigger="hover">
                            <i class="fab fa-google-drive ml-2" style="color:#CCCC66"></i>
                            </el-popover>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>