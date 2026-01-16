<template>
  <div class="tree">
    <ol class="headerTree">
      <li id="rowListHeader"  class="tree-header">
          <div class="tree-column">
            <!-- <a style="visibility: hidden;"><i class="fas fa-chevron-square-down fa-lg pr-2"></i></a> -->
            <div class="company-name-column cursor-pointer" @click="handleSort('company_name')">COMPANY NAME
              <span>
                <i v-if="sortOrder.company_name === 'ascending'" class="el-icon-caret-top"></i>
                <i v-if="sortOrder.company_name === 'descending'" class="el-icon-caret-bottom"></i>
                <i v-if="sortOrder.company_name === ''">
                  <i class="el-icon-caret-top"></i>
                  <i class="el-icon-caret-bottom"></i>
                </i>
              </span>
            </div>
            <div class="tools text-left cursor-pointer" @click="handleSort('full_name')">FULL NAME
              <span>
                <i v-if="sortOrder.full_name === 'ascending'" class="el-icon-caret-top"></i>
                <i v-if="sortOrder.full_name === 'descending'" class="el-icon-caret-bottom"></i>
                <i v-if="sortOrder.full_name === ''">
                  <i class="el-icon-caret-top"></i>
                  <i class="el-icon-caret-bottom"></i>
                </i>
              </span>
            </div>
            <div class="email-column text-left cursor-pointer" @click="handleSort('email')">E-MAIL
              <span>
                <i v-if="sortOrder.email === 'ascending'" class="el-icon-caret-top"></i>
                <i v-if="sortOrder.email === 'descending'" class="el-icon-caret-bottom"></i>
                <i v-if="sortOrder.email === ''">
                  <i class="el-icon-caret-top"></i>
                  <i class="el-icon-caret-bottom"></i>
                </i>
              </span>
            </div>
            <div class="col-action text-left">ACTION
              <el-popover
                placement="bottom"
                width="270"
                popper-class="custom-popover"
                trigger="click"
                v-model="popoverVisible">
                <!-- <i slot="reference" class="fa-solid fa-filter" style="margin-left: 8px; cursor: pointer;"></i> -->
                <i slot="reference" :class="['fa-solid', 'fa-filter', { 'text-info': isFilterActive } ]" style="margin-left: 8px; cursor: pointer;"></i>
                <div>
                  <p style="font-weight: 600; color: black !important;">Filters</p>
                  <div style="width: 100%; height: 1px; background-color: #ccc; margin: 16px 0px;"></div>
                  <el-checkbox @change="applyFilters" v-model="filters.directPayment.active">Direct Payment</el-checkbox>
                  <el-checkbox @change="applyFilters" v-model="filters.paymentFailed.active">Payment Failed</el-checkbox>
                  <el-collapse v-model="filterDefaultOpen" v-if="this.$global.rootcomp && companyRootID == this.$global.idsys"> 
                    <el-collapse-item title="Developer Api" name="1">
                      <el-checkbox @change="applyFilters" v-model="filters.isUserGenerateApi.active">API Key Generated</el-checkbox>
                      <el-checkbox @change="applyFilters" v-model="filters.isOpenApiMode.active">Developer Mode Enabled</el-checkbox>
                    </el-collapse-item>
                  </el-collapse>
                
                  <!-- <div class='d-flex justify-content-end mr-4 mt-4'>
                      <base-button @click="applyFilters" :simple="true" size="sm">
                        Save
                      </base-button>
                  </div> -->
                </div>
              </el-popover>
            </div>
            <div class="sales-column text-left">SALES</div>
            <div class="col-created text-left cursor-pointer" @click="handleSort('created_at')">CREATED
              <span>
                <i v-if="sortOrder.created_at === 'ascending'" class="el-icon-caret-top"></i>
                <i v-if="sortOrder.created_at === 'descending'" class="el-icon-caret-bottom"></i>
                <i v-if="sortOrder.created_at === '' || sortOrder.created_at === null">
                  <i class="el-icon-caret-top"></i>
                  <i class="el-icon-caret-bottom"></i>
                </i>
              </span>
            </div>
          </div>
      </li>
    </ol>
    <div class="el-table__empty-block" style="width: 1203px; height: 100%; overflow: hidden;" v-if="treeData.length == 0">
      <span class="el-table__empty-text mt-4">
        <i class="fas fa-spinner fa-pulse fa-2x d-block"></i>Loading data...
      </span>
    </div>
    <ol class="sortable sitemap-list ui-sortable ">
      <node-tree :companyRootID="companyRootID" :rootDomain="rootDomain" :mypackages="mypackages" :node="row" :index="index" v-for="(row,index) in treeData" :key="row.id" :isBestSales="isBestSales(row)" @clickPriceSet="priceSetClick" @clickSalesSet="salesSetClick" :GetDownlineList="GetDownlineList" :GetSalesDownlineList="GetSalesDownlineList" @clickUserLogs="UserLogsClick" @clickOnboardingCharge="onboardingChargeClick" :minimumSpendList="minimumSpendList"></node-tree>
    </ol>

    <!-- MODAL SALES PERSON -->
    <modal id="modalSalesSet" :show.sync="modals.salesSetup" headerClasses="justify-content-center">
       <h4 slot="header" class="title title-up">Set Sales Person for : <span style="color:#d42e66">{{AgencyCompanyName}}</span></h4>
       <div style="height:20px">&nbsp;</div>

        <div class="row">
            <div class="col-sm-12 col-md-6 col-lg-4" style="padding-block: 4px;">
              <div>
                <i class="fas fa-user pr-2"></i>Sales Representative
              </div>
              <div>
                <el-select
                    class="select-primary"
                    size="large"
                    placeholder="Select Sales Representative"
                    filterable
                    default-first-option
                    v-model="selects.salesRepSelected"
                    style="width: 90%; border: 1.5px solid black; border-radius: 5px;"
                >
                
                    <el-option
                        v-for="option in selects.salesList"
                        class="select-primary"
                        :value="option.id"
                        :label="option.name"
                        :key="option.id"
                    >
                    </el-option>
                </el-select>
                <a class="pl-2" href="#" v-on:click.prevent="removeSales('salesrep')"><i class="fas fa-times-circle fa-lg"></i></a>
              </div>
            </div>
            <div class="col-sm-12 col-md-6 col-lg-4" style="padding-block: 4px;">
              <div>
                <i class="fas fa-user-headset pr-2"></i>Account Executive
              </div>
              <div>
                  <el-select
                    class="select-primary"
                    size="large"
                    placeholder="Select Account Executive"
                    filterable
                    default-first-option
                    v-model="selects.salesAESelected"
                    style="width: 90%; border: 1.5px solid black; border-radius: 5px;"
                  >
                
                    <el-option
                        v-for="option in selects.salesList"
                        class="select-primary"
                        :value="option.id"
                        :label="option.name"
                        :key="option.id"
                    >
                    </el-option>
                </el-select>
                <a class="pl-2" href="#" v-on:click.prevent="removeSales('salesae')"><i class="fas fa-times-circle fa-lg"></i></a>
              </div>
            </div>
            <div class="col-sm-12 col-md-6 col-lg-4" style="padding-block: 4px;">
              <div>
                <i class="fas fa-user-tag pr-2"></i>Referral Account
              </div>
              <div>
                <el-select
                    class="select-primary"
                    size="large"
                    placeholder="Select Referral Account"
                    filterable
                    default-first-option
                    v-model="selects.salesRefSelected"
                    style="width: 90%; border: 1.5px solid black; border-radius: 5px;"
                >
                
                    <el-option
                        v-for="option in selects.salesList"
                        class="select-primary"
                        :value="option.id"
                        :label="option.name"
                        :key="option.id"
                    >
                    </el-option>
                </el-select>
                <a class="pl-2" href="#" v-on:click.prevent="removeSales('salesref')"><i class="fas fa-times-circle fa-lg"></i></a>
              </div>
          </div>
        </div>
        <template slot="footer">
          <div class="text-center pb-4 w-100">
            <base-button :disabled="isLoadingSalesSet" v-if="this.$global.settingMenuShow_update" style="min-width:120px"  @click.native="saveSalesSet()">
              Save <i v-if="isLoadingSalesSet" class="fas fa-spinner fa-spin ml-1" style="font-size: 16px;"></i>
            </base-button>
          </div>
        </template>
    </modal>
    <!-- MODAL SALES PERSON -->
                        <!-- Modal Setting Markup -->
                            <modal id="modalAgencySetPrice" :show.sync="modals.pricesetup" headerClasses="justify-content-center">
                              <h4 slot="header" class="title title-up text-title-wrapping">Set Cost for Agency: <span style="color:#d42e66">{{AgencyCompanyName}}</span></h4>
                              <!-- <p class="text-center">
                                <a href="#">Click here</a> to watch video if you need more explanation.
                              </p> -->
                              <div style="display:none">
                                <!--<iframe width="970" height="415" src="https://www.youtube.com/embed/SCSDyqRP7cY" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>-->
                              </div>
                              <div style="height:20px">&nbsp;</div>
                              <div class="row" v-show="activeMenuPrices != 'Clean ID'">
                                  <div class="col-sm-12 col-md-12 col-lg-12">
                                      <div class="d-inline-block pr-4" v-if="false">
                                            <label>Select Modules:</label>
                                            <el-select
                                                class="select-primary"
                                                size="large"
                                                placeholder="Select Modules"
                                                v-model="selectsAppModule.AppModuleSelect"
                                                style="padding-left:10px"
                                                >
                                                <el-option
                                                    v-for="option in selectsAppModule.AppModule"
                                                    class="select-primary"
                                                    :value="option.value"
                                                    :label="option.label"
                                                    :key="option.label"
                                                >
                                                </el-option>
                                            </el-select>
                                      </div>
                                      <div class="d-inline-block">
                                            <label>Payment Term:</label>
                                            <el-select
                                                class="select-primary"
                                                size="small"
                                                placeholder="Select Modules"
                                                v-model="selectsPaymentTerm.PaymentTermSelect"
                                                style="padding-left:10px"
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
                                  </div>
                              </div>
                              <div style="height:20px">&nbsp;</div>
                              <div style="padding-top: 16px; padding-inline: 16px;">
                                <div :class="[companyRootID == $global.masteridsys ? 'row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5' : 'row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4']">
                                  <div class="menu__prices" style="padding-inline: 16px;" v-if="selectsAppModule.AppModuleSelect == 'LeadsPeek'"  @click="onActiveMenuPrices($global.globalModulNameLink.local.name)" :class="[
                                    activeMenuPrices == $global.globalModulNameLink.local.name ? 'active__menu__prices' : '',
                                    'col'
                                    // cssDefaultModuleByLength(),
                                  ]">
                                    <div style="display: flex; justify-content: center;" v-html="$global.globalModulNameLink.local.name"></div>
                                  </div>
                                  
                                  <div class="menu__prices" @click="onActiveMenuPrices($global.globalModulNameLink.locator.name)" :class="[
                                    activeMenuPrices == $global.globalModulNameLink.locator.name ? 'active__menu__prices' : '',
                                    'col'
                                    // cssDefaultModuleByLength(),
                                  ]">
  
                                    <div style="display: flex; justify-content: center;" v-html="$global.globalModulNameLink.locator.name"></div>
                                  </div>
                                  
                                  <div 
                                    class="menu__prices" 
                                    @click="onActiveMenuPrices($global.globalModulNameLink.enhance.name)" 
                                    v-if="$global.globalModulNameLink.enhance.name != null && $global.globalModulNameLink.enhance.url != null" 
                                    :class="[
                                      activeMenuPrices == $global.globalModulNameLink.enhance.name ? 'active__menu__prices' : '',
                                      'col'
                                      // cssDefaultModuleByLength(),
                                    ]">
                                    <div style="display: flex; justify-content: center;" v-html="$global.globalModulNameLink.enhance.name"></div>
                                  </div>
  
                                  <div 
                                    class="menu__prices" 
                                    @click="onActiveMenuPrices($global.globalModulNameLink.b2b.name)" 
                                    v-if="$global.globalModulNameLink.b2b.name != null && $global.globalModulNameLink.b2b.url != null" 
                                    :class="[
                                      activeMenuPrices == $global.globalModulNameLink.b2b.name ? 'active__menu__prices' : '',
                                      'col'
                                      // cssDefaultModuleByLength(),
                                    ]">
                                    <div style="display: flex; justify-content: center;" v-html="$global.globalModulNameLink.b2b.name"></div>
                                  </div>
  
                                  <div 
                                    class="menu__prices" 
                                    @click="onActiveMenuPrices('Clean ID')"  
                                    v-if="companyRootID == $global.masteridsys"
                                    :class="[
                                      activeMenuPrices == 'Clean ID' ? 'active__menu__prices' : '',
                                      'col'
                                      // cssDefaultModuleByLength(),
                                    ]">
                                    <div style="display: flex; justify-content: center;">Clean ID</div>
                                  </div>
                                </div>

                                <div class="row" style="border: 1px solid gray; height: 1px; margin-top: 16px;"></div>

                                <div class="row">
                                  <!-- SITE ID -->
                                  <div class="col-12" style="margin-top: 16px; margin-bottom: 16px; padding-left: 0px; padding-right: 0px;" v-if="activeMenuPrices == $global.globalModulNameLink.local.name">
                                    <div class="row">
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                          How much base price for One Time Creative/Set Up Fee? 
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="LeadspeekPlatformFee"    
                                                  @keyup="set_fee('local','LeadspeekPlatformFee');"
                                                  @blur="handleFormatCurrency('local','LeadspeekPlatformFee')"
                                                  @keydown="restrictInput"  
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                        <div style="line-height:40px">
                                        How much base price <span v-html="txtLeadService">per month</span> are you charging your client for Platform Fee? 
                                        </div>
                                        <div>
                                          <base-input
                                            label=""
                                            type="text"
                                            placeholder="0"
                                            addon-left-icon="fas fa-dollar-sign"
                                            class="frmSetCost input__setup__prices campaign-cost-input"    
                                            v-model="LeadspeekMinCostMonth"    
                                            @keyup="set_fee('local','LeadspeekMinCostMonth');"
                                            @blur="handleFormatCurrency('local','LeadspeekMinCostMonth')"
                                            @keydown="restrictInput"      
                                            @copy.prevent @cut.prevent @paste.prevent
                                          >
                                          </base-input>
                                        </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                        <div style="line-height:40px">
                                          How much cost per contact <span v-html="txtLeadOver">from the monthly charge</span>? (Basic)
                                          <sup>
                                              <el-popover
                                                trigger="hover"
                                                :content="helpContentMap[0].desc"
                                                placement="top"
                                                popper-class="tooltip-content"
                                              >
                                                <span slot="reference" style="font-size: 12px;"><i class="fa fa-question-circle"></i></span>
                                              </el-popover>
                                          </sup>
                                          <!-- <sup>
                                            <span style="cursor: pointer; font-size: 12px;"><i @click="openHelpModal(0)" class="fa fa-question-circle"></i></span>
                                          </sup> -->
                                        </div>
                                        <div>
                                            <base-input
                                                label=""
                                                type="text"
                                                placeholder="0"
                                                addon-left-icon="fas fa-dollar-sign"
                                                class="frmSetCost input__setup__prices campaign-cost-input"    
                                                v-model="LeadspeekCostperlead"    
                                                @keyup="set_fee('local','LeadspeekCostperlead');"
                                                @blur="handleFormatCurrency('local','LeadspeekCostperlead')"
                                                @keydown="restrictInput"   
                                                @copy.prevent @cut.prevent @paste.prevent
                                            >
                                            </base-input>
                                        </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                        <div style="line-height:40px">
                                          How much cost per contact <span v-html="txtLeadOver">from the monthly charge</span>? (Advanced)
                                          <sup>
                                            <el-popover
                                              trigger="hover"
                                              :content="helpContentMap[1].desc"
                                              placement="top"
                                              popper-class="tooltip-content"
                                            >
                                            <span slot="reference" style="font-size: 12px;"><i class="fa fa-question-circle"></i></span>
                                            </el-popover>
                                          </sup>
                                          <!-- <sup>
                                            <span style="cursor: pointer; font-size: 12px;"><i @click="openHelpModal(1)" class="fa fa-question-circle"></i></span>
                                          </sup> -->
                                        </div>
                                        <div>
                                          <base-input
                                            label=""
                                            type="text"
                                            placeholder="0"
                                            addon-left-icon="fas fa-dollar-sign"
                                            class="frmSetCost input__setup__prices campaign-cost-input"    
                                            v-model="LeadspeekCostperleadAdvanced"
                                            @keyup="set_fee('local','LeadspeekCostperleadAdvanced');"
                                            @blur="handleFormatCurrency('local','LeadspeekCostperleadAdvanced')"  
                                            @keydown="restrictInput"   
                                            @copy.prevent @cut.prevent @paste.prevent>
                                          </base-input>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                  <!-- SITE ID -->
  
                                  <!-- SEARCH ID -->
                                  <div class="col-12" style="margin-top: 16px; margin-bottom: 16px; padding-left: 0px; padding-right: 0px;" v-if="activeMenuPrices == $global.globalModulNameLink.locator.name">
                                    <div class="row">
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                            How much base price for One Time Creative/Set Up Fee? 
                                            <!-- <sup>
                                              <span style="cursor: pointer; font-size: 12px;"><i @click="openHelpModal(0)" class="fa fa-question-circle"></i></span>
                                            </sup> -->
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="LocatorPlatformFee"    
                                                  @keyup="set_fee('locator','LocatorPlatformFee');"
                                                  @blur="handleFormatCurrency('locator','LocatorPlatformFee')"
                                                  @keydown="restrictInput"   
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                          How much base price <span v-html="txtLeadService">per month</span> are you charging your client for Platform Fee? 
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="LocatorMinCostMonth"    
                                                  @keyup="set_fee('locator','LocatorMinCostMonth');"
                                                  @blur="handleFormatCurrency('locator','LocatorMinCostMonth')"
                                                  @keydown="restrictInput"   
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div class="d-inline pr-3" style="float:left;line-height:40px">
                                          How much cost per contact <span v-html="txtLeadOver">from the monthly charge</span>?
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="LocatorCostperlead"    
                                                  @keyup="set_fee('locator','LocatorCostperlead');"
                                                  @blur="handleFormatCurrency('locator','LocatorCostperlead')"
                                                  @keydown="restrictInput"    
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                      </div>
                                    </div>
                                  </div>
                                  <!-- SEARCH ID -->
  
                                  <!-- ENHANCE ID -->
                                  <div class="col-12" style="margin-top: 16px; margin-bottom: 16px; padding-left: 0px; padding-right: 0px;" v-if="activeMenuPrices == $global.globalModulNameLink.enhance.name && $global.globalModulNameLink.enhance.name != null && $global.globalModulNameLink.enhance.url != null">
                                    <div class="row">
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                          How much base price for One Time Creative/Set Up Fee? 
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="EnhancePlatformFee"    
                                                  @keyup="set_fee('enhance','EnhancePlatformFee');"
                                                  @blur="handleFormatCurrency('enhance','EnhancePlatformFee')"
                                                  @keydown="restrictInput"   
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                          How much base price <span v-html="txtLeadService">per month</span> are you charging your client for Platform Fee? 
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="EnhanceMinCostMonth"    
                                                  @keyup="set_fee('enhance','EnhanceMinCostMonth');"
                                                  @blur="handleFormatCurrency('enhance','EnhanceMinCostMonth')"
                                                  @keydown="restrictInput"   
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                          How much cost per contact <span v-html="txtLeadOver">from the monthly charge</span>?
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="EnhanceCostperlead"    
                                                  @keyup="set_fee('enhance','EnhanceCostperlead');"
                                                  @blur="handleFormatCurrency('enhance','EnhanceCostperlead')"
                                                  @keydown="restrictInput"  
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                          <span v-if="errMinCostPerLead" style="color:#942434; font-size:12px;font-weight: 400;line-height: 40px;display: inline;margin-left: .5rem;">*Cost Per Lead Minimum {{ MinCostPerLead }}</span>
                                      </div>
                                    </div>
                                  </div>
                                  <!-- ENHANCE ID -->
                                  
                                  <!-- B2B ID -->
                                  <div class="col-12" style="margin-top: 16px; margin-bottom: 16px; padding-left: 0px; padding-right: 0px;" v-if="activeMenuPrices == $global.globalModulNameLink.b2b.name && $global.globalModulNameLink.b2b.name != null && $global.globalModulNameLink.b2b.url != null">
                                    <div class="row">
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                          How much base price for One Time Creative/Set Up Fee? 
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="B2bPlatformFee"    
                                                  @keyup="set_fee('b2b','B2bPlatformFee');"
                                                  @blur="handleFormatCurrency('b2b','B2bPlatformFee')"
                                                  @keydown="restrictInput"   
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                          How much base price <span v-html="txtLeadService">per month</span> are you charging your client for Platform Fee? 
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="B2bMinCostMonth"    
                                                  @keyup="set_fee('b2b','B2bMinCostMonth');"
                                                  @blur="handleFormatCurrency('b2b','B2bMinCostMonth')"
                                                  @keydown="restrictInput"   
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                      </div>
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                          <div style="line-height:40px">
                                          How much cost per contact <span v-html="txtLeadOver">from the monthly charge</span>?
                                          </div>
                                          <div>
                                              <base-input
                                                  label=""
                                                  type="text"
                                                  placeholder="0"
                                                  addon-left-icon="fas fa-dollar-sign"
                                                  class="frmSetCost input__setup__prices campaign-cost-input"    
                                                  v-model="B2bCostperlead"    
                                                  @keyup="set_fee('b2b','B2bCostperlead');"
                                                  @blur="handleFormatCurrency('b2b','B2bCostperlead')"
                                                  @keydown="restrictInput"  
                                                  @copy.prevent @cut.prevent @paste.prevent
                                              >
                                              </base-input>
                                          </div>
                                          <span v-if="errMinCostPerLead" style="color:#942434; font-size:12px;font-weight: 400;line-height: 40px;display: inline;margin-left: .5rem;">*Cost Per Lead Minimum {{ MinCostPerLead }}</span>
                                      </div>
                                    </div>
                                  </div>
                                  <!-- B2B ID -->
  
                                  <!-- CLEAN ID -->
                                  <div class="col-12" style="margin-top: 16px; margin-bottom: 16px; padding-left: 0px; padding-right: 0px;" v-if="activeMenuPrices == 'Clean ID' && companyRootID == $global.masteridsys">
                                    <div class="row">
                                      <div v-if="false" class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                        <div style="line-height:40px">
                                          How much cost per contact charge? (Basic)
                                          <sup>
                                            <el-popover
                                              trigger="hover"
                                              :content="helpContentMap[2].desc"
                                              placement="top"
                                              popper-class="tooltip-content"
                                            >
                                            <span slot="reference" style="font-size: 12px;"><i class="fa fa-question-circle"></i></span>
                                            </el-popover>
                                          </sup>
                                          <span v-if="errLeadspeekCleanCostperlead" style="font-size: 13px; color: #942434; margin-left: 16px;">* Cost per lead must be above 0</span>
                                          <!-- <sup>
                                            <span style="cursor: pointer; font-size: 12px;"><i @click="openHelpModal(0)" class="fa fa-question-circle"></i></span>
                                          </sup> -->
                                        </div>
                                        <div>
                                            <base-input
                                                label=""
                                                type="text"
                                                placeholder="0"
                                                addon-left-icon="fas fa-dollar-sign"
                                                class="frmSetCost input__setup__prices campaign-cost-input"    
                                                v-model="LeadspeekCleanCostperlead"    
                                                @keyup="set_fee('clean','LeadspeekCleanCostperlead');"
                                                @blur="handleFormatCurrency('clean','LeadspeekCleanCostperlead')"
                                                @keydown="restrictInput"   
                                                @copy.prevent @cut.prevent @paste.prevent
                                            >
                                            </base-input>
                                        </div>
                                    </div>
                                    <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                      <div class="col-sm-12 col-md-12 col-lg-12 container__setup__prices">
                                        <div style="line-height:40px">
                                          How much cost per contact charge? (Advanced)
                                          <sup>
                                            <el-popover
                                              trigger="hover"
                                              :content="helpContentMap[3].desc"
                                              placement="top"
                                              popper-class="tooltip-content"
                                            >
                                            <span slot="reference" style="font-size: 12px;"><i class="fa fa-question-circle"></i></span>
                                            </el-popover>
                                          </sup>
                                          <span v-if="errLeadspeekCleanCostperleadAdvanced" style="font-size: 13px; color: #942434; margin-left: 16px;">* Cost per lead advanced must be above 0</span>
                                          <!-- <sup>
                                            <span style="cursor: pointer; font-size: 12px;"><i @click="openHelpModal(1)" class="fa fa-question-circle"></i></span>
                                          </sup> -->
                                        </div>
                                        <div>
                                          <base-input
                                            label=""
                                            type="text"
                                            placeholder="0"
                                            addon-left-icon="fas fa-dollar-sign"
                                            class="frmSetCost input__setup__prices campaign-cost-input"    
                                            v-model="LeadspeekCleanCostperleadAdvanced"
                                            @keyup="set_fee('clean','LeadspeekCleanCostperleadAdvanced');"
                                            @blur="handleFormatCurrency('clean','LeadspeekCleanCostperleadAdvanced')"
                                            @keydown="restrictInput"   
                                            @copy.prevent @cut.prevent @paste.prevent>
                                          </base-input>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                  </div>
                                  <!-- CLEAN ID -->
                                </div>
                              </div>

                              <template slot="footer">
                                <div class="container text-center pb-4" >
                                  <base-button :disabled="isLoadingAgencyCost" v-if="this.$global.settingMenuShow_update" style="min-width:120px" @click.native="saveAgencyCost()">
                                    Save <i v-if="isLoadingAgencyCost" class="fas fa-spinner fa-spin ml-1" style="font-size: 16px;"></i>
                                  </base-button>
                                </div>
                              </template>
                            </modal>
                           <!-- Modal Setting Markup -->

  <!-- MODAL User Logs -->
    <modal id="modalAgencyUserLogs" :show.sync="modals.userlogs" headerClasses="justify-content-center">
      <div style="height:20px">&nbsp;</div>
      <h4 slot="header" class="title title-up text-title-wrapping">Agency User's Logs: <span style="color:#d42e66">{{AgencyCompanyName}}</span></h4>
      <div style="margin-bottom: 8px; gap: 8px;" class="container__agency__logs">
        <div>
          <p style="margin-bottom: 0px; font-size: 20px; font-weight: 600;">History User Logs</p>
        </div>
        <div style="display: flex;" class="container__filters__logs">
          <base-button size="sm" style="height:40px" @click="isOpenFilters = true; getUsers()">
          <i class="fas fa-cloud-download-alt pr-2"></i> Filters
        </base-button>
          <base-button size="sm" style="height:40px" @click="ExportLogsData()" >
          <i class="fas fa-cloud-download-alt pr-2"></i> Download Data
        </base-button>
      </div>
      </div>
      <TableUserLogs :data="tableDataUserLogs" @update:data="ChangePageUserLogs"/>
          <div class="row">
              <div class="col-sm-12 col-md-12 col-lg-12 d-flex justify-content-end">

              </div>
          </div>
          <div style="height:20px">&nbsp;</div>
      </modal>
  <!-- Modal User LOgs -->

  <!-- MODAL HELP -->
  <modal :show.sync="modals.help">
    <div slot="header" class="d-flex flex-column gap-4">
      <h4 class="title title-up">{{activeHelpItem.title}}</h4>
    </div>
    <div v-if="modals.help" v-html="activeHelpItem.embedVideoCode"></div>
    <p class="text-dark mt-4" style="font-size: 14px;" v-html="activeHelpItem.desc"></p>
  </modal>
  <!-- MODAL HELP -->

  <el-drawer
    :visible.sync="isOpenFilters"
    :with-header="false"
    >
    <div style="padding: 20px;">
      <div style="display: flex; justify-content: space-between; gap: 8px;">
        <p style="font-weight: 600; color: black; font-size: 20px;">Filters user logs</p>
        <i class="el-icon-close" @click="isOpenFilters = false" style="font-size: 20px; color: black; cursor: pointer;"></i>
      </div>
      <div style="display: flex; flex-direction: column; margin-top: 24px; gap: 16px;">
        <div>
          <p style="color: black;">Date Range</p>
              <el-date-picker
                v-model="logDateRange"
                type="daterange"
                align="right"
                unlink-panels
                range-separator="-"
                start-placeholder="Start date"
                end-placeholder="End date"
                @change="handleChangeDate()"
                :picker-options="pickerOptions"
                :value-format="'yyyy-MM-dd'">
              </el-date-picker>
        </div>
        <div>
          <p style="color: black;">User type</p>
          <el-select style="width: 100%;" v-model="selectedUserType" id="userTypeSelect" placeholder="Select user type" @change="handleUserTypeChange">
            <el-option
            v-for="(userType, index) in FilterUsersType"
            :key="index"
            :value="userType.value"
            :label="userType.label"
            class="select-primary"
            >
          </el-option>
        </el-select>
      </div>
      <div>
        <p style="color: black;">User</p>
          <el-select style="width: 100%;" v-model="selectedUser"
          filterable
           id="userSelect" placeholder="Select user" @change="handleUserChange"
          >
            <el-option
              v-for="(user, index) in FilterUsers"
              :key="user.value"
              :value="user.value"
              :label="user.label"
              class="select-primary"
              >
            </el-option>
          </el-select>
      </div>
      <div>
        <p style="color: black;">Action</p>
        <el-select style="width: 100%;" v-model="selectedCategory" id="categorySelect" placeholder="Select Category" @change="handleCategoryChange">
          <el-option
          v-for="(category, index) in FilterUserLogsCategories"
          :key="index"
          :value="category.value"
          :label="category.name"
          class="select-primary"
          >
        </el-option>
      </el-select>
      </div>
      <div>
        <p style="color: black;">Select by Campaign</p>
        <el-select style="width: 100%;" v-model="selectedCampaign" filterable id="campaignSelect" placeholder="Select Campaign" @change="handleCampaignChange">
          <el-option
          v-for="(campaign, index) in FilterUserCampaign"
          :key="index"
          :value="campaign.value"
          :label="campaign.label"
          class="select-primary"
          >
        </el-option>
      </el-select>
      </div>
      </div>
    </div>
  </el-drawer>
  </div>
</template>

<script>
import moment from "moment-timezone";
import swal from 'sweetalert2';
import NodeTree from "./NodeTree";
import { DatePicker, Select, Option, Drawer, Card, Input, Collapse, CollapseItem, Divider, Checkbox, Popover, Timeline, TimelineItem } from 'element-ui';
import { Modal } from 'src/components';
import TableUserLogs from '../Tables/TableUserLogs.vue'
import { formatCurrencyUSD } from '../../util/formatCurrencyUSD'

export default {
  name: 'Tree',
  props: {
    treeData: {},
    rootDomain: '',
    mypackages: {},
    GetDownlineList:{
      type: Function
    },
    GetSalesDownlineList:{
      type: Function
    },
    sortOrder: {
      company_name: '',
      full_name: '',
      email: '',
      created_at: '',
    },
    companyRootID: '',
    minimumSpendList: {
      type: [Object, Array]
    },
  },
  components: {
   NodeTree,
   Modal,
   TableUserLogs,
   [DatePicker.name]: DatePicker,
   [Option.name]: Option,
   [Select.name]: Select,
   [Drawer.name]: Drawer,
   [Card.name]: Card,
   [Input.name]: Input,
   [Collapse.name]: Collapse,
   [CollapseItem.name]: CollapseItem,
   [Divider.name]: Divider,
   [Checkbox.name]: Checkbox,
   [Popover.name]: Popover,
   [Timeline.name]: Timeline,
   [TimelineItem.name]: TimelineItem,
  },
  data() {
    return {
      isLoadingSalesSet : false,
      isLoadingAgencyCost : false,
      rootcostagency: '',
      MinCostPerLead: '',
      errMinCostPerLead: false,
      popoverVisible: false, 
      modals: {
        pricesetup: false,
        salesSetup: false,
        userlogs: false,
        directPayment: false,
        paymentFailed: false,
        help: false,
      },

      helpContentMap: [
        { 
          title: 'Site ID Basic Information',
          desc: "Site ID campaigns can use either Basic information or Advanced information, similar to Enhance ID. You must set the retail price for each of these data options independently of each other.",
          embedVideoCode: '<iframe src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/basicdata.jpeg" width="100%" height=180></iframe>'
        },
        {
          title: 'Site ID Advanced Information',
          desc: "Site ID campaigns can use either Basic information or Advanced information, similar to Enhance ID. You must set the retail price for each of these data options independently of each other.",
          embedVideoCode: '<iframe src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/advancedata.jpeg" width="100%" height=230></iframe>'
        },
        { 
          title: 'Clean ID Basic Information',
          desc: "Clean ID campaigns can use either Basic information or Advanced information, similar to Enhance ID. You must set the retail price for each of these data options independently of each other.",
          embedVideoCode: '<iframe src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/basicdata.jpeg" width="100%" height=180></iframe>'
        },
        {
          title: 'Clean ID Advanced Information',
          desc: "Clean ID campaigns can use either Basic information or Advanced information, similar to Enhance ID. You must set the retail price for each of these data options independently of each other.",
          embedVideoCode: '<iframe src="https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/advancedata.jpeg" width="100%" height=230></iframe>'
        }
      ],
      activeHelpItem:  {   
        title: '',
        embedVideoCode: '',
        desc: ''
      },

      tableDataUserLogs: {},
      logDateRange: '',
      pickerOptions: {
          shortcuts: [{
            text: 'Last week',
            onClick(picker) {
              const end = new Date();
              const start = new Date();
              start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
              picker.$emit('pick', [start, end]);
            }
          }, {
            text: 'Last month',
            onClick(picker) {
              const end = new Date();
              const start = new Date();
              start.setTime(start.getTime() - 3600 * 1000 * 24 * 30);
              picker.$emit('pick', [start, end]);
            }
          }, {
            text: 'Last 3 months',
            onClick(picker) {
              const end = new Date();
              const start = new Date();
              start.setTime(start.getTime() - 3600 * 1000 * 24 * 90);
              picker.$emit('pick', [start, end]);
            }
          }]
      },
      FilterUserLogsCategories: [ ],
      FilterUsersType: [
      {
          label: 'Select All',
          value: 9999,
        },
        {
          label: 'Enterprise',
          value: 'root',
        },
        {
          label: 'Admin',
          value: 'admin',
        },
        {
          label: 'Client',
          value: 'client',
        },
      ],
      FilterUsers: [],
      FilterUserCampaign: [],
      selectedUser: '',
      selectedUserType: 9999,
      selectedCategory: '',
      selectedCampaign: '',
      selectedCompanyIDUserLogs: '',
      userLogsCurrentPage: 1,

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

      LeadspeekCleanCostperlead: '0',
      LeadspeekCleanCostperleadAdvanced: '0',

      errLeadspeekCleanCostperlead: false,
      errLeadspeekCleanCostperleadAdvanced: false,
      
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
        }
      },

      txtLeadService: 'per week',
      txtLeadIncluded: 'in that weekly charge',
      txtLeadOver: 'from the weekly charge',

      selectsPaymentTerm: {
          PaymentTermSelect: 'Weekly',
          PaymentTerm: [
              // { value: 'One Time', label: 'One Time'},
              // { value: 'Weekly', label: 'Weekly'},
              // { value: 'Monthly', label: 'Monthly'},
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

      selects: {
        salesList: [],
        salesRepSelected: "",
        salesAESelected: "",
        salesRefSelected: "",
      },

      salesRowUpdate:0,
      activeMenuPrices: this.$global.globalModulNameLink.local.name,
      isOpenFilters: false,
      selectedRowData: {},
      filters: {
        directPayment:{
          active: false,
        },
        paymentFailed:{
          active: false,
        },
        isUserGenerateApi: {
          active: false
        },
        isOpenApiMode : {
          active : false
        }
      },
      filterDefaultOpen: ['1']
    }
  },
  computed: {
    isFilterActive() {
      return this.filters.directPayment.active || this.filters.paymentFailed.active;
    }
  },
  methods: {
    openHelpModal(index) {
      this.activeHelpItem = this.helpContentMap[index]
      this.modals.help = true;
    },
    cssDefaultModuleByLength() {
      if(this.$global.globalModulNameLink.enhance.name != null && this.$global.globalModulNameLink.enhance.url != null && this.$global.globalModulNameLink.b2b.name != null && this.$global.globalModulNameLink.b2b.url != null) {
        return 'col-sm-6 col-md-3 col-lg-3';
      } else if(this.$global.globalModulNameLink.enhance.name == null && this.$global.globalModulNameLink.enhance.url == null && this.$global.globalModulNameLink.b2b.name == null && this.$global.globalModulNameLink.b2b.url == null) {
        return 'col-sm-6 col-md-6 col-lg-6';
      } else if(this.$global.globalModulNameLink.enhance.name != null && this.$global.globalModulNameLink.enhance.url != null) {
        return 'col-sm-6 col-md-4 col-lg-4';
      } else if(this.$global.globalModulNameLink.b2b.name != null && this.$global.globalModulNameLink.b2b.url != null) {
        return 'col-sm-6 col-md-4 col-lg-4';
      }
    },
    handleVisibleChange(visible) {            
            this.dropdownVisible = visible;
    },
    applyFilters(event){
            // event.stopPropagation(); // Prevent click propagation
            // this.$emit('on-filters', this.filters);
            // // this.$refs.popoverRef.hide();
            // this.popoverVisible = false; 
            this.$emit('on-filters', this.filters);
            this.popoverVisible = false;
    },
    validateMinCostCleanPerLead(type) {
      if(type == 'LeadspeekCleanCostperlead') {
        if(Number(this.LeadspeekCleanCostperlead) <= 0) {
          this.LeadspeekCleanCostperlead = this.rootcostagency.clean.CleanCostperlead;
          this.errLeadspeekCleanCostperlead = false;
        }
      } else if(type == 'LeadspeekCleanCostperleadAdvanced') {
        if(Number(this.LeadspeekCleanCostperleadAdvanced) <= 0) {          
          this.LeadspeekCleanCostperleadAdvanced = this.rootcostagency.clean.CleanCostperleadAdvanced;
          this.errLeadspeekCleanCostperleadAdvanced = false;
        }
      }
    },
    validateMinCostPerLead(){
      /* ENHANCE */
      if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly' && (Number(this.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Weekly.EnhanceCostperlead))) {
        this.errMinCostPerLead = false;
        this.EnhanceCostperlead = this.rootcostagency.enhance.Weekly.EnhanceCostperlead;
        this.costagency.enhance.Weekly.EnhanceCostperlead = this.rootcostagency.enhance.Weekly.EnhanceCostperlead;
      } else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly' && (Number(this.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Monthly.EnhanceCostperlead))) {
        this.errMinCostPerLead = false;
        this.EnhanceCostperlead = this.rootcostagency.enhance.Monthly.EnhanceCostperlead;
        this.costagency.enhance.Monthly.EnhanceCostperlead = this.rootcostagency.enhance.Monthly.EnhanceCostperlead;
      } else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time' && (Number(this.EnhanceCostperlead) < Number(this.rootcostagency.enhance.OneTime.EnhanceCostperlead))) {
        this.errMinCostPerLead = false;
        this.EnhanceCostperlead = this.rootcostagency.enhance.OneTime.EnhanceCostperlead;
        this.costagency.enhance.OneTime.EnhanceCostperlead = this.rootcostagency.enhance.OneTime.EnhanceCostperlead;
      } else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid' && (Number(this.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Prepaid.EnhanceCostperlead))) {
        this.errMinCostPerLead = false;
        this.EnhanceCostperlead = this.rootcostagency.enhance.Prepaid.EnhanceCostperlead;
        this.costagency.enhance.Prepaid.EnhanceCostperlead = this.rootcostagency.enhance.Prepaid.EnhanceCostperlead;
      }
      /* ENHANCE */

      /* B2B */
      if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly' && (Number(this.B2bCostperlead) < Number(this.rootcostagency.b2b.Weekly.B2bCostperlead))) {
        this.errMinCostPerLead = false;
        this.B2bCostperlead = this.rootcostagency.b2b.Weekly.B2bCostperlead;
        this.costagency.b2b.Weekly.B2bCostperlead = this.rootcostagency.b2b.Weekly.B2bCostperlead;
      } else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly' && (Number(this.B2bCostperlead) < Number(this.rootcostagency.b2b.Monthly.B2bCostperlead))) {
        this.errMinCostPerLead = false;
        this.B2bCostperlead = this.rootcostagency.b2b.Monthly.B2bCostperlead;
        this.costagency.b2b.Monthly.B2bCostperlead = this.rootcostagency.b2b.Monthly.B2bCostperlead;
      } else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time' && (Number(this.B2bCostperlead) < Number(this.rootcostagency.b2b.OneTime.B2bCostperlead))) {
        this.errMinCostPerLead = false;
        this.B2bCostperlead = this.rootcostagency.b2b.OneTime.B2bCostperlead;
        this.costagency.b2b.OneTime.B2bCostperlead = this.rootcostagency.b2b.OneTime.B2bCostperlead;
      } else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid' && (Number(this.B2bCostperlead) < Number(this.rootcostagency.b2b.Prepaid.B2bCostperlead))) {
        this.errMinCostPerLead = false;
        this.B2bCostperlead = this.rootcostagency.b2b.Prepaid.B2bCostperlead;
        this.costagency.b2b.Prepaid.B2bCostperlead = this.rootcostagency.b2b.Prepaid.B2bCostperlead;
      }
      /* B2B */
    },
    removeSales(type) {
      if (type == "salesrep") {
        this.selects.salesRepSelected = "";
      }else if (type == "salesae") {
        this.selects.salesAESelected = "";
      }else if (type == "salesref") {
        this.selects.salesRefSelected = "";
      }
    },
    getSalesList(value,index) {
      this.$store.dispatch('getSalesList',{
        companyID: this.$global.idsys,
      }).then(response => {
        if (this.selects.salesList.length == 0) {
          this.selects.salesList = response.params
        }
        this.selects.salesRepSelected = value.salesrepid;
        this.selects.salesAESelected = value.accountexecutiveid;
        this.selects.salesRefSelected = value.accountrefid;
        this.salesRowUpdate = index;
        this.modals.salesSetup = true;
      },error => {
          
      });
    },
    set_fee(type,typevalue) {

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

        }else if (type == 'enhance') {
          if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
            if (typevalue == 'EnhancePlatformFee') {
              this.costagency.enhance.Weekly.EnhancePlatformFee = this.EnhancePlatformFee;
            }else if (typevalue == 'EnhanceCostperlead') {
              // if(Number(this.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Weekly.EnhanceCostperlead)) {
              //   this.errMinCostPerLead = true;
              //   this.MinCostPerLead = this.rootcostagency.enhance.Weekly.EnhanceCostperlead
              // } else {
              //   this.errMinCostPerLead = false;
              // }
              this.costagency.enhance.Weekly.EnhanceCostperlead = this.EnhanceCostperlead;
            }else if (typevalue == 'EnhanceMinCostMonth') {
              this.costagency.enhance.Weekly.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
            }
          }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
            if (typevalue == 'EnhancePlatformFee') {
              this.costagency.enhance.Monthly.EnhancePlatformFee = this.EnhancePlatformFee;
            }else if (typevalue == 'EnhanceCostperlead') {
              // if(Number(this.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Monthly.EnhanceCostperlead)) {
              //   this.errMinCostPerLead = true;
              //   this.MinCostPerLead = this.rootcostagency.enhance.Monthly.EnhanceCostperlead
              // } else {
              //   this.errMinCostPerLead = false;
              // }
              this.costagency.enhance.Monthly.EnhanceCostperlead = this.EnhanceCostperlead;
            }else if (typevalue == 'EnhanceMinCostMonth') {
              this.costagency.enhance.Monthly.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
            }
          }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
            if (typevalue == 'EnhancePlatformFee') {
              this.costagency.enhance.OneTime.EnhancePlatformFee = this.EnhancePlatformFee;
            }else if (typevalue == 'EnhanceCostperlead') {
              // if(Number(this.EnhanceCostperlead) < Number(this.rootcostagency.enhance.OneTime.EnhanceCostperlead)) {
              //   this.errMinCostPerLead = true;
              //   this.MinCostPerLead = this.rootcostagency.enhance.OneTime.EnhanceCostperlead
              // } else {
              //   this.errMinCostPerLead = false;
              // }
              this.costagency.enhance.OneTime.EnhanceCostperlead = this.EnhanceCostperlead;
            }else if (typevalue == 'EnhanceMinCostMonth') {
              this.costagency.enhance.OneTime.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
            }
          }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                if (typevalue == 'EnhancePlatformFee') {
                    this.costagency.enhance.Prepaid.EnhancePlatformFee = this.EnhancePlatformFee;
                }else if (typevalue == 'EnhanceCostperlead') {
                  //  if(Number(this.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Prepaid.EnhanceCostperlead)) {
                  //     this.errMinCostPerLead = true;
                  //     this.MinCostPerLead = this.rootcostagency.enhance.Prepaid.EnhanceCostperlead
                  //   } else {
                  //     this.errMinCostPerLead = false;
                  //   }
                    this.costagency.enhance.Prepaid.EnhanceCostperlead = this.EnhanceCostperlead;
                }else if (typevalue == 'EnhanceMinCostMonth') {
                    this.costagency.enhance.Prepaid.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
                }
            }

        }else if (type == 'b2b') {
          if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
            if (typevalue == 'B2bPlatformFee') {
              this.costagency.b2b.Weekly.B2bPlatformFee = this.B2bPlatformFee;
            }else if (typevalue == 'B2bCostperlead') {
              // if(Number(this.B2bCostperlead) < Number(this.rootcostagency.b2b.Weekly.B2bCostperlead)) {
              //   this.errMinCostPerLead = true;
              //   this.MinCostPerLead = this.rootcostagency.b2b.Weekly.B2bCostperlead
              // } else {
              //   this.errMinCostPerLead = false;
              // }
              this.costagency.b2b.Weekly.B2bCostperlead = this.B2bCostperlead;
            }else if (typevalue == 'B2bMinCostMonth') {
              this.costagency.b2b.Weekly.B2bMinCostMonth = this.B2bMinCostMonth;
            }
          }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
            if (typevalue == 'B2bPlatformFee') {
              this.costagency.b2b.Monthly.B2bPlatformFee = this.B2bPlatformFee;
            }else if (typevalue == 'B2bCostperlead') {
              // if(Number(this.B2bCostperlead) < Number(this.rootcostagency.b2b.Monthly.B2bCostperlead)) {
              //   this.errMinCostPerLead = true;
              //   this.MinCostPerLead = this.rootcostagency.b2b.Monthly.B2bCostperlead
              // } else {
              //   this.errMinCostPerLead = false;
              // }
              this.costagency.b2b.Monthly.B2bCostperlead = this.B2bCostperlead;
            }else if (typevalue == 'B2bMinCostMonth') {
              this.costagency.b2b.Monthly.B2bMinCostMonth = this.B2bMinCostMonth;
            }
          }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
            if (typevalue == 'B2bPlatformFee') {
              this.costagency.b2b.OneTime.B2bPlatformFee = this.B2bPlatformFee;
            }else if (typevalue == 'B2bCostperlead') {
              // if(Number(this.B2bCostperlead) < Number(this.rootcostagency.b2b.OneTime.B2bCostperlead)) {
              //   this.errMinCostPerLead = true;
              //   this.MinCostPerLead = this.rootcostagency.b2b.OneTime.B2bCostperlead
              // } else {
              //   this.errMinCostPerLead = false;
              // }
              this.costagency.b2b.OneTime.B2bCostperlead = this.B2bCostperlead;
            }else if (typevalue == 'B2bMinCostMonth') {
              this.costagency.b2b.OneTime.B2bMinCostMonth = this.B2bMinCostMonth;
            }
          }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
            if (typevalue == 'B2bPlatformFee') {
              this.costagency.b2b.Prepaid.B2bPlatformFee = this.B2bPlatformFee;
            }else if (typevalue == 'B2bCostperlead') {
              // if(Number(this.B2bCostperlead) < Number(this.rootcostagency.b2b.Prepaid.B2bCostperlead)) {
              //   this.errMinCostPerLead = true;
              //   this.MinCostPerLead = this.rootcostagency.b2b.Prepaid.B2bCostperlead
              // } else {
              //   this.errMinCostPerLead = false;
              // }
              this.costagency.b2b.Prepaid.B2bCostperlead = this.B2bCostperlead;
            }else if (typevalue == 'B2bMinCostMonth') {
              this.costagency.b2b.Prepaid.B2bMinCostMonth = this.B2bMinCostMonth;
            }
          }
        }else if (type == 'clean') {
          if (typevalue == 'LeadspeekCleanCostperlead') {
            this.costagency.clean.CleanCostperlead = this.LeadspeekCleanCostperlead;
            // this.errLeadspeekCleanCostperlead = (Number(this.costagency.clean.CleanCostperlead) === 0);
          } else if (typevalue == 'LeadspeekCleanCostperleadAdvanced') {
            this.costagency.clean.CleanCostperleadAdvanced = this.LeadspeekCleanCostperleadAdvanced;
            // this.errLeadspeekCleanCostperleadAdvanced = (Number(this.costagency.clean.CleanCostperleadAdvanced) === 0);
          }
        }
       
    },
    resetAgencyCost() {

      this.LeadspeekPlatformFee = '0';
      this.LeadspeekCostperlead = '0';
      this.LeadspeekMinCostMonth = '0';
      this.LocatorPlatformFee = '0';
      this.LocatorCostperlead = '0';
      this.LocatorMinCostMonth = '0';

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
    saveSalesSet() {
        this.isLoadingSalesSet = true
        this.$store.dispatch('setSalesPerson', {
            companyID: this.CompanyActiveID,
            salesRep: this.selects.salesRepSelected,
            salesAE: this.selects.salesAESelected,
            salesRef: this.selects.salesRefSelected,
        }).then(response => {
            if (response.result == "success") {
                this.$notify({
                    type: 'success',
                    message: 'Setting has been saved.',
                    icon: 'tim-icons icon-bell-55'
                });  

                var datasales = this.selects.salesList;
                var rowIndex = this.salesRowUpdate;

                let arr = [];
                var index = 0;
                var indexSR = 0;
                var indexAE = 0;
                var indexRF = 0;

                if (this.selects.salesRepSelected != '') {
                  arr.push(this.selects.salesRepSelected );
                  indexSR = index;
                  index++;
                }
                if (this.selects.salesAESelected != '') {
                  arr.push(this.selects.salesAESelected);
                  indexAE = index;
                  index++;
                }
                 if (this.selects.salesRefSelected != '') {
                  arr.push(this.selects.salesRefSelected);
                  indexRF = index;
                  index++;
                }
                let res = arr.map((id) => (datasales.find(x => x.id == id).name));
                
                var salesreps = res[indexSR];
                var accountexecutive = res[indexAE];
                var accountref = res[indexRF];

                var finalsales = "";
                if (this.selects.salesRepSelected != '') {
                  finalsales = finalsales + '<i class="fas fa-user pr-2"></i>' + salesreps + '<br/>';
                  this.treeData[rowIndex].salesrep = salesreps;
                  this.treeData[rowIndex].salesrepid = this.selects.salesRepSelected;
                }else{
                  this.treeData[rowIndex].salesrep = "";
                  this.treeData[rowIndex].salesrepid = "";
                }

                if (this.selects.salesAESelected != '') {
                  finalsales = finalsales + '<i class="fas fa-user-headset pr-2"></i>' +accountexecutive + '<br/>';
                  this.treeData[rowIndex].accountexecutive = accountexecutive;
                  this.treeData[rowIndex].accountexecutiveid = this.selects.salesAESelected;
                }else{
                  this.treeData[rowIndex].accountexecutive = "";
                  this.treeData[rowIndex].accountexecutiveid = "";
                }

                if (this.selects.salesRefSelected != '') {
                  finalsales = finalsales + '<i class="fas fa-user-tag pr-2"></i>' + accountref + '<br/>';
                  this.treeData[rowIndex].accountref = accountref;
                  this.treeData[rowIndex].accountrefid = this.selects.salesRefSelected;
                }else{
                  this.treeData[rowIndex].accountref = "";
                  this.treeData[rowIndex].accountrefid = "";
                }

                if (finalsales != "") {
                  finalsales = '<div style="line-height:30px;padding-top:10px;padding-bottom:10px">' + finalsales + '</div>';
                }
                $('#salesperson_' + this.CompanyActiveID).html(finalsales);

                if (this.selects.salesRepSelected == "" && this.selects.salesAESelected == ""  && this.selects.salesRefSelected == "") {
                  $('#iconsalesperson_' + this.CompanyActiveID).css('color',"gray");
                }else{
                  $('#iconsalesperson_' + this.CompanyActiveID).css('color',"orange");
                }

                const userData = this.$store.getters.userData
                if(userData.user_type == 'sales'){
                  this.GetSalesDownlineList('created_at', 'descending')
                } else {
                  this.GetDownlineList('created_at', 'descending')
                }
                
                this.modals.salesSetup = false;
              }
              this.isLoadingSalesSet = false
        },error => {
              this.isLoadingSalesSet = false
        });
      //}else{
      //  this.modals.salesSetup = false;
      //}
    },
    saveAgencyCost() {
      this.isLoadingAgencyCost = true
      // validation whencost clean id 0
      // if(Number(this.costagency.clean.CleanCostperlead) <= 0) {
      //   this.isLoadingAgencyCost = false
      //   this.$notify({
      //     type: 'danger',
      //     message: 'Cost per lead in clean id must be above 0',
      //     icon: 'fas fa-bug'
      //   });
      //   return;
      // } 
      // if(Number(this.costagency.clean.CleanCostperleadAdvanced) <= 0) {
      //   this.isLoadingAgencyCost = false
      //   this.$notify({
      //     type: 'danger',
      //     message: 'Cost per lead advanced in clean id must be above 0',
      //     icon: 'fas fa-bug'
      //   });
      //   return;
      // }
      // validation whencost clean id 0
      this.$store.dispatch('updateGeneralSetting', {
          companyID: this.CompanyActiveID,
          actionType: 'customsmtpmodule',
          comsetname: 'costagency',
          comsetval: this.costagency,
      }).then(response => {
          if (response.result == "success") {
              this.$notify({
                  type: 'success',
                  message: 'Setting has been saved.',
                  icon: 'tim-icons icon-bell-55'
              });  

              this.modals.pricesetup = false;
            }
            this.isLoadingAgencyCost = false
      },error => {
            this.isLoadingAgencyCost = false
      });
    },
    salesSetClick(value,index) {
      //console.log(value);
      this.AgencyCompanyName = value.company_name;
      this.CompanyActiveID = value.company_id;
      this.getSalesList(value,index);
    },
    ExportLogsData(){
      let company_id = this.selectedCompanyIDUserLogs != '' && this.selectedCompanyIDUserLogs != null  && this.selectedCompanyIDUserLogs != undefined ? this.selectedCompanyIDUserLogs : 9999 
      let user_type = this.selectedUserType != '' && this.selectedUserType != null  && this.selectedUserType != undefined ? this.selectedUserType : 9999 
      let user_id = this.selectedUser != '' && this.selectedUser != null  && this.selectedUser != undefined ? this.selectedUser : 9999 
      let action = this.selectedCategory != '' && this.selectedCategory != null  && this.selectedCategory != undefined ? this.selectedCategory : 9999 
      let date_range = this.logDateRange != '' && this.logDateRange != null  && this.logDateRange != undefined ? this.logDateRange : 'all' 
      let leadspeek_api_id = this.selectedCampaign != '' && this.selectedCampaign != null  && this.selectedCampaign != undefined ? this.selectedCampaign : 'all' 
      if(company_id != '') {
        document.location = process.env.VUE_APP_DATASERVER_URL + '/configuration/user/download-log-user/' + company_id + '/' + user_type + '/' + user_id + '/' + action + '/' + date_range + '/' + leadspeek_api_id;
      }
    },
    getUserLogsCategory(){
      this.$store.dispatch('getUserLogsCategory')
      .then(response => {
        if (response) {
          const category = response;
          this.FilterUserLogsCategories = category.length > 0
          ? [{ name: 'All action', value: '' }, ...category.map(res => ({ name: res.action, value: res.action }))]
          : [];
        }
      },error => {
        this.FilterUserLogsCategories = []    
      });
    },
    UserLogsClick(value){
      this.userLogsCurrentPage = 1
      this.tableDataUserLogs = {
        ...this.tableDataUserLogs,
        data: [],
      }
      this.AgencyCompanyName = value.company_name
      this.modals.userlogs = true;
      this.selectedCompanyIDUserLogs = value.company_id
      this.selectedUserType = 9999
      this.getUserLogsCategory()
      this.getUserLogs()
      this.getUserCampaigns()
    },
    currentDate() {
          const current = new Date();
          var _month = current.getMonth()+1;
          var _year = current.getFullYear();
          var _date = current.getDate();

          _month = ('0' + _month).slice(-2);
          _date = ('0' + _date).slice(-2);

          const date = `${current.getFullYear()}-${_month}-${_date}`;
          return date;
    },
    getUserCampaigns(){
      this.$store.dispatch('getUserCampaigns',{
        companyID :this.selectedCompanyIDUserLogs,
      })
      .then(response => {
        if (response.campaigns.length > 0) {
              const campaigns = response.campaigns

              this.FilterUserCampaign = [
                { label: 'All Campaign', value: ''}, 
                ...campaigns.map(campaign => ({
                  label: `${campaign.leadspeek_api_id} - ${campaign.campaign_name} - ${campaign.company_name}`,
                  value: campaign.leadspeek_api_id
                }))
              ];
        }else{
        }
      },error => {
      });
    },
    getUserLogs(){
      this.$store.dispatch('getUserLogs', {
          companyID: this.selectedCompanyIDUserLogs,
          page: this.userLogsCurrentPage,
          action: this.selectedCategory,
          userType: this.selectedUserType,
          userID: this.selectedUser,
          campaignID: this.selectedCampaign,
          logDateRange: this.logDateRange,
      }).then(response => {  
        if (response) {
          this.tableDataUserLogs = response
        }
      },error => {
        this.tableDataUserLogs = {
        ...this.tableDataUserLogs,
          data: [],
        }

 
      });
    },
    ChangePageUserLogs(value){
      this.userLogsCurrentPage = value
      this.tableDataUserLogs = {
        ...this.tableDataUserLogs,
        data: [],
      }
      this.getUserLogs()
    },
    getUsers(){
      this.$store.dispatch('getUsers',{
        companyID :this.selectedCompanyIDUserLogs,
        userType :this.selectedUserType,
        searchUser :this.searchUser,
      })
      .then(response => {
        if (response.length > 0) {
              const users = response
              this.FilterUsers = [
                { label: 'All users', value: ''}, 
                ...users.map(user => ({
                  label: `${user.name} - ${user.company_name} - ${user.type_user_tx}`,
                  value: user.id
                }))
              ];
        }else{
          this.FilterUsers = [];
        }
        this.isUserSearchLoading = false
      },error => {
        this.FilterUsers = []    
        this.isUserSearchLoading = false
      });
    },
    handleChangeDate(){
      this.tableDataUserLogs =  { ...this.tableDataUserLogs, data: [] };
      this.userLogsCurrentPage = 1
      this.getUserLogs()
    },
    handleUserTypeChange(value) {
      this.tableDataUserLogs = {
        ...this.tableDataUserLogs,
        data: [],
      }
      this.userLogsCurrentPage = 1
      this.selectedUserType = value
      this.selectedUser = ''
      this.searchUser = ''
      this.getUsers()
      this.getUserLogs()
    },
    handleUserSearch(value){
      this.isUserSearchLoading = true
      this.searchUser = value
      this.getUsers()
    },
    handleUserBlur(){
      this.searchUser = ''
      this.getUsers()
    },
    handleUserChange(value) {
      this.tableDataUserLogs = {
        ...this.tableDataUserLogs,
        data: [],
      }
      this.userLogsCurrentPage = 1
      this.selectedUser = value
      this.getUserLogs()
    },
    handleCategoryChange(value) {
      this.tableDataUserLogs = {
        ...this.tableDataUserLogs,
        data: [],
      }
      this.userLogsCurrentPage = 1
      this.selectedCategory = value
      this.getUserLogs()
    },
    handleCampaignChange(value) {
      this.tableDataUserLogs =  { ...this.tableDataUserLogs, data: [] };
      this.selectedCampaign = value
      this.getUserLogs()
    },
    async onboardingChargeClick(value) {

      let current_amount = null;
      let enable_coupon = true;
      await this.$store.dispatch('getOnboardAgencySettings', {
          companyID: value.company_id,
          companyRootID: value.company_root_id,
          settingname: 'agencyonboardingcustom',
      }).then(response => {
          
          if (response.result == 'success') {
            current_amount = response.data.amount
            enable_coupon = response.data.enable_coupon
          }
      },error => {
            current_amount = null
            enable_coupon = true
      });

      const title = "Set Onboarding Charge Agency"
      const confirmButtonText = "Save"
      swal.fire({
                title: title,
                text: 'set the amount of charge',
                icon: '',
                showCancelButton: true,
                customClass: {
                confirmButton: 'btn btn-fill mr-3',
                cancelButton: 'btn btn-danger btn-fill'
                },
                confirmButtonText: confirmButtonText,
                html: `
                  <input id="swal-onboarding-charge-amount" type="number" step="0.01" class="swal2-input" placeholder="Amount in $" value="${current_amount}" />
                  <div style="text-align:center; margin: 0 1.5em;">
                    <label style="font-weight:normal;">
                      <input id="swal-coupon-enable" type="checkbox" style="margin-right:8px;"  ${enable_coupon == true ? 'checked' : ''} />
                      Enable coupon
                    </label>
                  </div>
                  <small style="display:block; margin-top:8px; color: #666;">
                    Current amount to charge is <strong>${current_amount}</strong>.<br />
                    Set amount to <strong>0</strong> to disable the onboarding charge.
                  </small>
                `,
                preConfirm: () => {
                  const amount = parseFloat(document.getElementById('swal-onboarding-charge-amount').value);
                  const enable_coupon = document.getElementById('swal-coupon-enable').checked;
                  if (amount > 0 && amount <= 0.5) {
                    swal.showValidationMessage('Amount can not be less than or equal to 0.5.');
                    return false;
                  }
                  if (Number.isNaN(amount)) {
                    swal.showValidationMessage('Please enter a valid amount.');
                    return false;
                  }

                  if (amount < 0) {
                    swal.showValidationMessage('Amount cannot be negative.');
                    return false;
                  }

                  if (amount > 0 && amount <= 0.5) {
                    swal.showValidationMessage('Amount must be greater than 0.5 if not zero.');
                    return false;
                  }

                  return {
                    amount: amount,
                    enable_coupon: enable_coupon,
                  }
                }
        }).then(result => {
                if (result.isConfirmed) {

                  /** SET AGENCY TO EXCLUDE FROM ONBOARDING CHARGE */
                  this.$store.dispatch('setAgencyOnboardingCharge', {
                      CompanyID: value.company_id,
                      amount: result.value.amount,
                      enable_coupon: result.value.enable_coupon
                  }).then(response => {
                      
                      if (response.status != null) {
                        value.exclude_onboard_charge = response.status;
                      }                      

                      this.$notify({
                            type: response.result == 'success' ? 'success' : 'danger',
                            message: response.message,
                            icon: 'tim-icons icon-bell-55'
                      });  

                  },error => {
                      this.$notify({
                            type: 'warning',
                            message: 'We are unable to save your setting, please try again later.',
                            icon: 'tim-icons icon-bell-55'
                      });  
                  });
                  /** SET AGENCY TO EXCLUDE FROM ONBOARDING CHARGE */
                }
        });
    },
    priceSetClick(value) {
      this.activeMenuPrices = this.$global.globalModulNameLink.local.name,
      this.AgencyCompanyName = value.company_name;
      this.resetAgencyCost();

      this.CompanyActiveID = value.company_id;
      this.$store.dispatch('getGeneralSetting', {
          companyID: value.company_id,
          settingname: 'costagency',
          idSys: this.companyRootID
      }).then(response => {
          //console.log(response.data);
          if (response.data != '') {
            this.costagency = response.data;
            this.rootcostagency = response.rootcostagency;
            this.selectsPaymentTerm.PaymentTermSelect = response.dpay ? response.dpay : 'Weekly';

            /* ENHANCE */
            //  if(Number(this.costagency.enhance.Weekly.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Weekly.EnhanceCostperlead)) {
            //   this.costagency.enhance.Weekly.EnhanceCostperlead = this.rootcostagency.enhance.Weekly.EnhanceCostperlead;
            //  }
            //  if(Number(this.costagency.enhance.Monthly.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Monthly.EnhanceCostperlead)) {
            //   this.costagency.enhance.Monthly.EnhanceCostperlead = this.rootcostagency.enhance.Monthly.EnhanceCostperlead;
            //  }
            //  if(Number(this.costagency.enhance.OneTime.EnhanceCostperlead) < Number(this.rootcostagency.enhance.OneTime.EnhanceCostperlead)) {
            //   this.costagency.enhance.OneTime.EnhanceCostperlead = this.rootcostagency.enhance.OneTime.EnhanceCostperlead;
            //  }
            //  if(Number(this.costagency.enhance.Prepaid.EnhanceCostperlead) < Number(this.rootcostagency.enhance.Prepaid.EnhanceCostperlead)) {
            //   this.costagency.enhance.Prepaid.EnhanceCostperlead = this.rootcostagency.enhance.Prepaid.EnhanceCostperlead;
            //  }
            /* ENHANCE */

            /* B2B */
            // if(Number(this.costagency.b2b.Weekly.B2bCostperlead) < Number(this.rootcostagency.b2b.Weekly.B2bCostperlead)) {
            //   this.costagency.b2b.Weekly.B2bCostperlead = this.rootcostagency.b2b.Weekly.B2bCostperlead;
            // }
            //  if(Number(this.costagency.b2b.Monthly.B2bCostperlead) < Number(this.rootcostagency.b2b.Monthly.B2bCostperlead)) {
            //   this.costagency.b2b.Monthly.B2bCostperlead = this.rootcostagency.b2b.Monthly.B2bCostperlead;
            // }
            // if(Number(this.costagency.b2b.OneTime.B2bCostperlead) < Number(this.rootcostagency.b2b.OneTime.B2bCostperlead)) {
            //   this.costagency.b2b.OneTime.B2bCostperlead = this.rootcostagency.b2b.OneTime.B2bCostperlead;
            // }
            // if(Number(this.costagency.b2b.Prepaid.B2bCostperlead) < Number(this.rootcostagency.b2b.Prepaid.B2bCostperlead)) {
            //   this.costagency.b2b.Prepaid.B2bCostperlead = this.rootcostagency.b2b.Prepaid.B2bCostperlead;
            // }
            /* B2B */
             
             if (response.dpay == 'Weekly') {
              this.txtLeadService = 'per week';
              this.txtLeadIncluded = 'in that weekly charge';
              this.txtLeadOver ='from the weekly charge';
              
              this.LeadspeekPlatformFee = this.costagency.local.Weekly.LeadspeekPlatformFee;
              this.LeadspeekCostperlead = this.costagency.local.Weekly.LeadspeekCostperlead;
              this.LeadspeekCostperleadAdvanced = this.costagency.local.Weekly.LeadspeekCostperleadAdvanced;
              this.LeadspeekMinCostMonth = this.costagency.local.Weekly.LeadspeekMinCostMonth;

              this.LocatorPlatformFee  = this.costagency.locator.Weekly.LocatorPlatformFee;
              this.LocatorCostperlead = this.costagency.locator.Weekly.LocatorCostperlead;
              this.LocatorMinCostMonth = this.costagency.locator.Weekly.LocatorMinCostMonth

              this.EnhancePlatformFee  = this.costagency.enhance.Weekly.EnhancePlatformFee;
              this.EnhanceCostperlead = this.costagency.enhance.Weekly.EnhanceCostperlead;
              this.EnhanceMinCostMonth = this.costagency.enhance.Weekly.EnhanceMinCostMonth
              
              this.B2bPlatformFee  = this.costagency.b2b.Weekly.B2bPlatformFee;
              this.B2bCostperlead = this.costagency.b2b.Weekly.B2bCostperlead;
              this.B2bMinCostMonth = this.costagency.b2b.Weekly.B2bMinCostMonth
             }else if (response.dpay == 'Monthly') {
              this.txtLeadService = 'per month';
              this.txtLeadIncluded = 'in that monthly charge';
              this.txtLeadOver ='from the monthly charge';

              this.LeadspeekPlatformFee = this.costagency.local.Monthly.LeadspeekPlatformFee;
              this.LeadspeekCostperlead = this.costagency.local.Monthly.LeadspeekCostperlead;
              this.LeadspeekCostperleadAdvanced = this.costagency.local.Monthly.LeadspeekCostperleadAdvanced;
              this.LeadspeekMinCostMonth = this.costagency.local.Monthly.LeadspeekMinCostMonth;

              this.LocatorPlatformFee  = this.costagency.locator.Monthly.LocatorPlatformFee;
              this.LocatorCostperlead = this.costagency.locator.Monthly.LocatorCostperlead;
              this.LocatorMinCostMonth = this.costagency.locator.Monthly.LocatorMinCostMonth

              this.EnhancePlatformFee  = this.costagency.enhance.Monthly.EnhancePlatformFee;
              this.EnhanceCostperlead = this.costagency.enhance.Monthly.EnhanceCostperlead;
              this.EnhanceMinCostMonth = this.costagency.enhance.Monthly.EnhanceMinCostMonth
              
              this.B2bPlatformFee  = this.costagency.b2b.Monthly.B2bPlatformFee;
              this.B2bCostperlead = this.costagency.b2b.Monthly.B2bCostperlead;
              this.B2bMinCostMonth = this.costagency.b2b.Monthly.B2bMinCostMonth
             }

             if (typeof(this.costagency.local.Prepaid) == 'undefined') {
                this.$set(this.costagency.local,'Prepaid',{
                  LeadspeekPlatformFee: '0',
                  LeadspeekCostperlead: '0',
                  LeadspeekCostperleadAdvanced: '0',
                  LeadspeekMinCostMonth: '0',
                });
             }

             if (typeof(this.costagency.locator.Prepaid) == 'undefined') {
                this.$set(this.costagency.locator,'Prepaid',{
                  LocatorPlatformFee: '0',
                  LocatorCostperlead: '0',
                  LocatorMinCostMonth: '0',
                });
             }

             if (typeof(this.costagency.enhance.Prepaid) == 'undefined') {
                this.$set(this.costagency.enhance,'Prepaid',{
                  EnhancePlatformFee: '0',
                  EnhanceCostperlead: '0',
                  EnhanceMinCostMonth: '0',
                });
             }
             
             if (typeof(this.costagency.b2b.Prepaid) == 'undefined') {
                this.$set(this.costagency.b2b,'Prepaid',{
                  B2bPlatformFee: '0',
                  B2bCostperlead: '0',
                  B2bMinCostMonth: '0',
                });
             }

             if (response.dpay == 'Prepaid') {
              this.txtLeadService = 'per month';
              this.txtLeadIncluded = 'in that monthly charge';
              this.txtLeadOver ='from the monthly charge';

              this.LeadspeekPlatformFee = this.costagency.local.Prepaid.LeadspeekPlatformFee;
              this.LeadspeekCostperlead = this.costagency.local.Prepaid.LeadspeekCostperlead;
              this.LeadspeekCostperleadAdvanced = this.costagency.local.Prepaid.LeadspeekCostperleadAdvanced;
              this.LeadspeekMinCostMonth = this.costagency.local.Prepaid.LeadspeekMinCostMonth;

              this.LocatorPlatformFee  = this.costagency.locator.Prepaid.LocatorPlatformFee;
              this.LocatorCostperlead = this.costagency.locator.Prepaid.LocatorCostperlead;
              this.LocatorMinCostMonth = this.costagency.locator.Prepaid.LocatorMinCostMonth

              this.EnhancePlatformFee  = this.costagency.enhance.Prepaid.EnhancePlatformFee;
              this.EnhanceCostperlead = this.costagency.enhance.Prepaid.EnhanceCostperlead;
              this.EnhanceMinCostMonth = this.costagency.enhance.Prepaid.EnhanceMinCostMonth

              this.B2bPlatformFee  = this.costagency.b2b.Prepaid.B2bPlatformFee;
              this.B2bCostperlead = this.costagency.b2b.Prepaid.B2bCostperlead;
              this.B2bMinCostMonth = this.costagency.b2b.Prepaid.B2bMinCostMonth
             }

             this.LeadspeekCleanCostperlead = this.costagency.clean.CleanCostperlead;
             this.LeadspeekCleanCostperleadAdvanced = this.costagency.clean.CleanCostperleadAdvanced;
          }
          this.modals.pricesetup = true;
      },error => {
            
      });
      
    },
    paymentTermChange() {
        if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
            this.txtLeadService = 'per week';
            this.txtLeadIncluded = 'in that weekly charge';
            this.txtLeadOver ='from the weekly charge';

            /** SET VALUE */
             this.LeadspeekPlatformFee = this.costagency.local.Weekly.LeadspeekPlatformFee;
             this.LeadspeekCostperlead = this.costagency.local.Weekly.LeadspeekCostperlead;
             this.LeadspeekCostperleadAdvanced = this.costagency.local.Weekly.LeadspeekCostperleadAdvanced;
             this.LeadspeekMinCostMonth = this.costagency.local.Weekly.LeadspeekMinCostMonth;

             this.LocatorPlatformFee  = this.costagency.locator.Weekly.LocatorPlatformFee;
             this.LocatorCostperlead = this.costagency.locator.Weekly.LocatorCostperlead;
             this.LocatorMinCostMonth = this.costagency.locator.Weekly.LocatorMinCostMonth
             
             this.EnhancePlatformFee  = this.costagency.enhance.Weekly.EnhancePlatformFee;
             this.EnhanceCostperlead = this.costagency.enhance.Weekly.EnhanceCostperlead;
             this.EnhanceMinCostMonth = this.costagency.enhance.Weekly.EnhanceMinCostMonth
             
             this.B2bPlatformFee  = this.costagency.b2b.Weekly.B2bPlatformFee;
             this.B2bCostperlead = this.costagency.b2b.Weekly.B2bCostperlead;
             this.B2bMinCostMonth = this.costagency.b2b.Weekly.B2bMinCostMonth

            /** SET VALUE */
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
            this.txtLeadService = 'per month';
            this.txtLeadIncluded = 'in that monthly charge';
            this.txtLeadOver ='from the monthly charge';

            /** SET VALUE */
             this.LeadspeekPlatformFee = this.costagency.local.Monthly.LeadspeekPlatformFee;
             this.LeadspeekCostperlead = this.costagency.local.Monthly.LeadspeekCostperlead;
             this.LeadspeekCostperleadAdvanced = this.costagency.local.Monthly.LeadspeekCostperleadAdvanced;
             this.LeadspeekMinCostMonth = this.costagency.local.Monthly.LeadspeekMinCostMonth;
             
             this.LocatorPlatformFee  = this.costagency.locator.Monthly.LocatorPlatformFee;
             this.LocatorCostperlead = this.costagency.locator.Monthly.LocatorCostperlead;
             this.LocatorMinCostMonth = this.costagency.locator.Monthly.LocatorMinCostMonth

             this.EnhancePlatformFee  = this.costagency.enhance.Monthly.EnhancePlatformFee;
             this.EnhanceCostperlead = this.costagency.enhance.Monthly.EnhanceCostperlead;
             this.EnhanceMinCostMonth = this.costagency.enhance.Monthly.EnhanceMinCostMonth
             
             this.B2bPlatformFee  = this.costagency.b2b.Monthly.B2bPlatformFee;
             this.B2bCostperlead = this.costagency.b2b.Monthly.B2bCostperlead;
             this.B2bMinCostMonth = this.costagency.b2b.Monthly.B2bMinCostMonth
            /** SET VALUE */
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
             this.LocatorMinCostMonth = this.costagency.locator.OneTime.LocatorMinCostMonth

             this.EnhancePlatformFee  = this.costagency.enhance.OneTime.EnhancePlatformFee;
             this.EnhanceCostperlead = this.costagency.enhance.OneTime.EnhanceCostperlead;
             this.EnhanceMinCostMonth = this.costagency.enhance.OneTime.EnhanceMinCostMonth

             this.B2bPlatformFee  = this.costagency.b2b.OneTime.B2bPlatformFee;
             this.B2bCostperlead = this.costagency.b2b.OneTime.B2bCostperlead;
             this.B2bMinCostMonth = this.costagency.b2b.OneTime.B2bMinCostMonth
            /** SET VALUE */

        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
            this.txtLeadService = 'per month';
            this.txtLeadIncluded = 'in that monthly charge';
            this.txtLeadOver ='from the monthly charge';

            /** SET VALUE */

            this.LeadspeekPlatformFee = (typeof(this.costagency.local.Prepaid) !== 'undefined')?this.costagency.local.Prepaid.LeadspeekPlatformFee:0;
            this.LeadspeekCostperlead = (typeof(this.costagency.local.Prepaid) !== 'undefined')?this.costagency.local.Prepaid.LeadspeekCostperlead:0;
            this.LeadspeekCostperleadAdvanced = (typeof(this.costagency.local.Prepaid) != 'undefined')?this.costagency.local.Prepaid.LeadspeekCostperleadAdvanced:0;
            this.LeadspeekMinCostMonth = (typeof(this.costagency.local.Prepaid) !== 'undefined')?this.costagency.local.Prepaid.LeadspeekMinCostMonth:0;
            
            this.LocatorPlatformFee  = (typeof(this.costagency.locator.Prepaid) !== 'undefined')?this.costagency.locator.Prepaid.LocatorPlatformFee:0;
            this.LocatorCostperlead = (typeof(this.costagency.locator.Prepaid) !== 'undefined')?this.costagency.locator.Prepaid.LocatorCostperlead:0;
            this.LocatorMinCostMonth = (typeof(this.costagency.locator.Prepaid) !== 'undefined')?this.costagency.locator.Prepaid.LocatorMinCostMonth:0;
            
            this.EnhancePlatformFee  = (typeof(this.costagency.enhance.Prepaid) !== 'undefined')?this.costagency.enhance.Prepaid.EnhancePlatformFee:0;
            this.EnhanceCostperlead = (typeof(this.costagency.enhance.Prepaid) !== 'undefined')?this.costagency.enhance.Prepaid.EnhanceCostperlead:0;
            this.EnhanceMinCostMonth = (typeof(this.costagency.enhance.Prepaid) !== 'undefined')?this.costagency.enhance.Prepaid.EnhanceMinCostMonth:0;
            
            this.B2bPlatformFee  = (typeof(this.costagency.b2b.Prepaid) !== 'undefined')?this.costagency.b2b.Prepaid.B2bPlatformFee:0;
            this.B2bCostperlead = (typeof(this.costagency.b2b.Prepaid) !== 'undefined')?this.costagency.b2b.Prepaid.B2bCostperlead:0;
            this.B2bMinCostMonth = (typeof(this.costagency.b2b.Prepaid) !== 'undefined')?this.costagency.b2b.Prepaid.B2bMinCostMonth:0;
            
            /** SET VALUE */

        }
    },
    handleSort(column) {
      // Reset other columns' sortOrder to ''
      for (let key in this.sortOrder) {
        if (key !== column) {
          this.sortOrder[key] = '';
        }
      }
      // Toggle sort order for the clicked column
      if (this.sortOrder[column] === '' || this.sortOrder[column] === null) {
        this.sortOrder[column] = 'ascending';
      } else if (this.sortOrder[column] === 'ascending') {
        this.sortOrder[column] = 'descending';
      } else {
        this.sortOrder[column] = '';
      }

      if(column == 'created_at' && this.sortOrder[column] == ''){
        this.sortOrder[column] = null
      }
      
      this.$emit('update-order-by', this.sortOrder[column])
      const userData = this.$store.getters.userData
      if(userData.user_type == 'sales'){
        this.GetSalesDownlineList(column, this.sortOrder[column])
      } else {
        this.GetDownlineList(column, this.sortOrder[column])
      }
    },
    isBestSales(row) {
      return !!(row.salesrepid || row.accountexecutiveid || row.accountrefid);
    },
    onActiveMenuPrices(value){
      this.activeMenuPrices = value
    },
    handleFormatCurrency(type, field){
      const validInput = /^[0-9]*(\.[0-9]*)?$/;

      if(!validInput.test(this[field])){
        this[field] = 0
      }

      // if(field == 'EnhanceCostperlead' || field == 'B2bCostperlead'){
      //   this.validateMinCostPerLead()
      // }
      // if(field == 'LeadspeekCleanCostperlead' || field == 'LeadspeekCleanCostperleadAdvanced') {
      //   this.validateMinCostCleanPerLead(field);
      // }

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
  },
  mounted() {
    this.selectsPaymentTerm.PaymentTerm = this.$global.rootpaymentterm;
  },
};
</script>

<style>
#modalAgencySetPrice .select-primary.el-select .el-input input, h3 {
  color: #525f7f;
}

#modalSalesSet .select-primary.el-select .el-input input, h3 {
  color: #525f7f;
}

#modalAgencySetPrice input:read-only {
    background-color: white;
}

#modalAgencySetPrice .el-input__prefix, #modalSetPrice .el-input__suffix {
    color: #525f7f;
}

#modalAgencySetPrice .leadlimitdate {
    width: auto !important;
}

#modalAgencySetPrice .el-input__inner {
    background-color: transparent;
    border-width: 1px;
    border-color: #2b3553;
    color: #942434;
}

.input__setup__prices .input-group .input-group-prepend .input-group-text i {
    color: #525f7f;
}

.input__setup__prices .input-group input[type=text],.input__setup__prices .input-group .input-group-prepend .input-group-text {
    color: #cad1d7;
    border-color: #cad1d7;
    border: 1px solid #cad1d7;
    padding: 10px;
}

.headerTree {
  width: 100%;
  margin:0;
  padding:0;
}

ol.sortable {
  margin:0;
  padding:0;
}

.list-drag {
  padding-right: 10px;
}

.treecolumn {
  min-width: 320px;
  max-width: 320px;
  text-align: left;
}
.email-column {
  min-width: 350px;
  max-width: 350px;
  text-align: left;
}
.sales-column {
  min-width: 180px;
  max-width: 180px;
  text-align: left;
}
.tree{
  overflow-x: auto;
}
.col-created {
  min-width: 120px;
  text-align: center;
}

.col-action {
  min-width: 220px;
  text-align: center;
  margin-left: 25px;
}


.tools {
  /* margin-left: auto; */
  justify-content: center;
  min-width: 250px;
  text-align: left;
}

.col-action .action {
  padding-right:10px;
}

.tree-header .tree-column div {
   color: var(--text-primary-color);
   font-size: 12px;  
   text-transform: uppercase;
   font-weight: 700;
}

ol {
  width: 100%;
  margin: 0;
}


ol li {
  display: flex;
  flex-wrap: wrap;
  line-height: 50px;
}

ol li > ol {
  /*background-color:green;*/
  margin:0;
}

/*ol li > ol li .tree-column div:first-child{
  padding-left: 20px;
}*/


.tree-column {
  width: 100%;
  display: flex;
  justify-content: space-between;
   border-bottom: 1px solid grey;
   color: var(--text-primary-color) !important;
  font-size: 14px;
}

.placeholder {
	 outline: 1px dashed #4183C4;
}

.node-tree .row {
  width: 100%;
}
.node-tree{
  /* width: max-content; */
}
/*.tree-list ul {
  padding-left: 16px;
  margin: 6px 0;
}*/

/*.tree-list .node-tree {
    display: table-row;
    color: rgba(255, 255, 255, 0.7);

}

.tree-list .tree-header{
    display: table-row;
    color: rgba(255, 255, 255, 0.7);
    font-size: 12px;  
    text-transform: uppercase;
    font-weight: 700;
}

.tree-list .tree-header span {
    border:solid;
    text-align: left;
    display: table-cell;
    padding: 6px;
    vertical-align: middle;
    
}

.tree-list .node-tree span {
    border:solid;
    text-align: left;
    display: table-cell;
    padding: 6px;
    vertical-align: middle;
    
}
*/
.company-name-column{
  min-width: 220px;
  max-width: 220px;
}

.card_continer_setup_price {
  border: 1px solid #ebeef5;
  border-radius: 4px;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, .1);
}

.menu__prices {
  padding: 8px 16px;
  border-radius: 4px;
  color: gray;
  cursor: pointer;
  font-weight: 600;
  font-size: 18px;
  border: 1px solid transparent;
  transition: border 300ms ease;
}

.active__menu__prices {
  color: black;
  border: 1px solid #222a42;
}

.container__setup__prices {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.container__agency__logs {
  display: flex;
  justify-content: space-between;
}

.container__filters__logs {
  display: flex;
  justify-content: flex-end;
  gap: 16px;
}

.input__setup__prices {
  width: 120px;
}

.active-amount {
  border: 1px solid #409EFF;
}

.tooltip-content {
  max-width: 300px;
  white-space: normal;
  word-break: break-word;
}


@media screen and (max-width: 767px) {
  .container__setup__prices {
    display: block;
  }
  .input__setup__prices {
    width: 100%;
  }
  .container__agency__logs {
    display: flex;
    flex-direction: column;
    justify-content: baseline;
  }
  .container__filters__logs {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 4px;
  }
}
</style>