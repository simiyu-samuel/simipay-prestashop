{extends file='page.tpl'}
{block name='page_title'}{l s='Pay with M-PESA' mod='kopokopo'}{/block}
{block name='page_content'}
<section class="kopokopo-payment-panel card card-block">
  <div class="kopokopo-payment-heading"><span class="kopokopo-payment-symbol" aria-hidden="true">M</span><strong>M-PESA</strong></div>
  {if $sandbox}<p class="alert alert-info">{l s='Sandbox simulation: no money is moved and no phone PIN prompt is sent.' mod='kopokopo'}</p>{/if}
  <p>{l s='Amount to pay:' mod='kopokopo'} <strong>{$payment_total|escape:'html':'UTF-8'}</strong></p>
  {if $sandbox}
    <p>{l s='Enter a Kenyan test mobile number. Kopo Kopo will simulate the payment result.' mod='kopokopo'}</p>
  {else}
    <p>{l s='Enter your Kenyan mobile number. You will receive an M-PESA prompt on your phone. Enter your PIN on your phone only.' mod='kopokopo'}</p>
  {/if}
  <form action="{$payment_action|escape:'html':'UTF-8'}" method="post">
    <input type="hidden" name="form_token" value="{$form_token|escape:'html':'UTF-8'}">
    <div class="form-group">
      <label for="kopokopo-phone">{l s='M-PESA phone number' mod='kopokopo'}</label>
      <input class="form-control" id="kopokopo-phone" type="tel" name="phone_number" inputmode="tel"
        autocomplete="tel" placeholder="0712345678" maxlength="24" aria-describedby="kopokopo-phone-help" required>
      <small id="kopokopo-phone-help">{l s='Accepted formats: 07…, 01…, 254… or +254…' mod='kopokopo'}</small>
    </div>
    <button class="btn btn-primary" type="submit">{if $sandbox}{l s='Simulate M-PESA payment' mod='kopokopo'}{else}{l s='Send M-PESA prompt' mod='kopokopo'}{/if}</button>
  </form>
  {include file='module:kopokopo/views/templates/_partials/attribution.tpl'}
</section>
{/block}
