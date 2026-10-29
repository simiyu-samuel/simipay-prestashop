<div class="kopokopo-payment-result">
{if $kopokopo_paid}
  <p class="alert alert-success">{l s='Your M-PESA payment was received. Thank you for your order.' mod='kopokopo'}</p>
{else}
  <p class="alert alert-info">{l s='Please contact the merchant to check your M-PESA payment.' mod='kopokopo'}</p>
{/if}
{include file='module:kopokopo/views/templates/_partials/attribution.tpl'}
</div>
