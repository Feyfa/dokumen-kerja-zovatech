<template>
  <div class="payment-alert-container" v-if="hasPrepaidFailed">
    <div class="payment-alert-card payment-alert-card--prepaid">
      <div class="payment-alert-card__header" @click="toggleSection('prepaid')">
        <i class="fas fa-pause-circle payment-alert-card__icon"></i>
        <h4 class="payment-alert-card__title">Failed Auto Top-up for Prepaid Campaigns</h4>
        <i class="fas payment-alert-card__chevron" :class="sectionsExpanded.prepaid ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
      </div>
      <div class="payment-alert-card__body" v-show="sectionsExpanded.prepaid">
        <p class="payment-alert-card__description">The following campaigns were paused due to failed auto top-up. Continuous top-up has been stopped for these campaigns:</p>
        <div class="payment-alert-card__list">
          <div class="payment-alert-card__item payment-alert-card__item--prepaid" v-for="(campaignId, index) in failedPrepaidCampaignIds" :key="index">
            <span class="payment-alert-card__campaign-id">Campaign #{{ campaignId }}</span>
          </div>
        </div>
        <p class="payment-alert-card__message">Please update your credit card information to reactivate these campaigns and resume continuous top-up.</p>
      </div>
    </div>
  </div>
</template>