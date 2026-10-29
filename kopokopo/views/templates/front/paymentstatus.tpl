{extends file='page.tpl'}
{block name='page_title'}{l s='M-PESA payment status' mod='kopokopo'}{/block}
{block name='page_content'}
<section class="card card-block" id="kopokopo-status" data-status-url="{$status_url|escape:'html':'UTF-8'}" data-state="{$payment_status.state|escape:'html':'UTF-8'}">
  <p id="kopokopo-message" role="status" aria-live="polite">{$payment_status.message|escape:'html':'UTF-8'}</p>
  <p>{l s='Payment reference:' mod='kopokopo'} <code>{$payment_status.reference|escape:'html':'UTF-8'}</code></p>
  <p>{l s='Keep this reference if you contact the merchant. You can refresh this page to check again.' mod='kopokopo'}</p>
  {if $payment_status.receipt}<p>{l s='M-PESA receipt:' mod='kopokopo'} {$payment_status.receipt|escape:'html':'UTF-8'}</p>{/if}
  {if $payment_status.redirect}
    <a class="btn btn-primary" href="{$payment_status.redirect|escape:'html':'UTF-8'}">{l s='View your order' mod='kopokopo'}</a>
  {/if}
  {if $payment_status.state == 'failed'}
    <a class="btn btn-primary" href="{$checkout_url|escape:'html':'UTF-8'}">{l s='Return to checkout' mod='kopokopo'}</a>
  {/if}
  <noscript><p>{l s='Refresh this page to see the latest payment status.' mod='kopokopo'}</p></noscript>
</section>
{/block}
