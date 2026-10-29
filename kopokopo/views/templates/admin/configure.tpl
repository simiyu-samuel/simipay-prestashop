<div class="panel">
  <h3>{l s='Kopo Kopo M-PESA' mod='kopokopo'}</h3>
  <p>{l s='PrestaShop 9.2 · PHP 8.2–8.5 · KES · single store. No code editing or Composer installation is required.' mod='kopokopo'}</p>
  {foreach $requirements as $requirement}<p class="alert alert-danger">{$requirement|escape:'html':'UTF-8'}</p>{/foreach}
  <form method="post" action="{$form_action|escape:'html':'UTF-8'}">
    <input type="hidden" name="form_token" value="{$form_token|escape:'html':'UTF-8'}">
    <div class="form-group">
      <label for="k2-environment">{l s='Environment' mod='kopokopo'}</label>
      <select class="form-control" name="environment" id="k2-environment">
        <option value="sandbox" {if $environment == 'sandbox'}selected{/if}>{l s='Sandbox (testing)' mod='kopokopo'}</option>
        <option value="production" {if $environment == 'production'}selected{/if}>{l s='Production (real payments)' mod='kopokopo'}</option>
      </select>
      <p class="help-block">{l s='To switch environments, select it and save first. Then enter its credentials and save again. Each environment has separate settings.' mod='kopokopo'}</p>
    </div>
    <div class="form-group"><label for="k2-till">{l s='Till number' mod='kopokopo'}</label>
      <input class="form-control" id="k2-till" name="till" value="{$till|escape:'html':'UTF-8'}" maxlength="32" autocomplete="off"></div>
    <div class="form-group"><label for="k2-client">{l s='OAuth client ID' mod='kopokopo'}</label>
      <input class="form-control" id="k2-client" name="client_id" value="{$client_id|escape:'html':'UTF-8'}" maxlength="512" autocomplete="off"></div>
    <div class="form-group"><label for="k2-secret">{l s='OAuth client secret' mod='kopokopo'}</label>
      <input class="form-control" id="k2-secret" type="password" name="client_secret" value="" maxlength="512" autocomplete="new-password">
      <p class="help-block">{l s='Leave blank to keep the saved secret.' mod='kopokopo'}</p></div>
    <div class="form-group"><label for="k2-key">{l s='API key (callback signing key)' mod='kopokopo'}</label>
      <input class="form-control" id="k2-key" type="password" name="api_key" value="" maxlength="512" autocomplete="new-password">
      <p class="help-block">{l s='Leave blank to keep the saved key. This is separate from the OAuth client secret.' mod='kopokopo'}</p></div>
    <button class="btn btn-primary" name="kopokopo_action" value="save" type="submit">{l s='Save settings' mod='kopokopo'}</button>
  </form>
  <hr>
  <form method="post" action="{$form_action|escape:'html':'UTF-8'}">
    <input type="hidden" name="form_token" value="{$form_token|escape:'html':'UTF-8'}">
    <button class="btn btn-default" name="kopokopo_action" value="test" type="submit">{l s='Test saved connection' mod='kopokopo'}</button>
  </form>
  <p>{l s='Start in sandbox. A connection test checks authentication; also test a complete checkout and callback before enabling production.' mod='kopokopo'}</p>
</div>
<div class="panel">
  <h3>{l s='Payment recovery' mod='kopokopo'}</h3>
  <p>{l s='Callbacks normally update payments automatically. Configure your hosting scheduler to check unresolved requests every minute, including after the shopper closes the browser.' mod='kopokopo'}</p>
  <p>{l s='Ask your host to schedule this command every minute. Keep its token private.' mod='kopokopo'}</p>
  <pre>curl --fail --silent --show-error --max-time 90 -X POST -H 'X-Kopokopo-Cron-Token: {$cron_token|escape:'html':'UTF-8'}' '{$cron_url|escape:'html':'UTF-8'}'</pre>
  <form method="post" action="{$form_action|escape:'html':'UTF-8'}">
    <input type="hidden" name="form_token" value="{$form_token|escape:'html':'UTF-8'}">
    <div class="form-group"><label for="k2-attempt">{l s='Payment reference' mod='kopokopo'}</label>
      <input class="form-control" id="k2-attempt" name="attempt_id" maxlength="32" required></div>
    <button class="btn btn-default" name="kopokopo_action" value="reconcile" type="submit">{l s='Check provider status' mod='kopokopo'}</button>
    <div class="form-group"><label for="k2-provider">{l s='Provider request UUID (only for a lost initiation response)' mod='kopokopo'}</label>
      <input class="form-control" id="k2-provider" name="provider_id" maxlength="36"></div>
    <button class="btn btn-default" name="kopokopo_action" value="recover" type="submit">{l s='Recover provider request' mod='kopokopo'}</button>
  </form>
  <p>{l s='If an attempt has no provider UUID, find its request in Kopo Kopo using the payment reference in its metadata. Recovery verifies that metadata before linking the request. Never resend an unresolved payment.' mod='kopokopo'}</p>
</div>
<div class="panel">
  <h3>{l s='Recent payment attempts' mod='kopokopo'}</h3>
  <p>{l s='Review means the merchant must reconcile funds and the order manually. No further payment should be requested. Full callback payloads and phone numbers are not logged.' mod='kopokopo'}</p>
  <div class="table-responsive"><table class="table">
    <thead><tr><th>{l s='Reference' mod='kopokopo'}</th><th>{l s='Environment' mod='kopokopo'}</th><th>{l s='Cart' mod='kopokopo'}</th><th>{l s='Amount (minor units)' mod='kopokopo'}</th><th>{l s='State' mod='kopokopo'}</th><th>{l s='M-PESA receipt' mod='kopokopo'}</th><th>{l s='Reason' mod='kopokopo'}</th><th>{l s='Created (UTC)' mod='kopokopo'}</th></tr></thead>
    <tbody>{foreach $attempts as $attempt}<tr>
      <td><code>{$attempt.id|escape:'html':'UTF-8'}</code></td><td>{$attempt.environment|escape:'html':'UTF-8'}</td><td>{$attempt.cart_id|intval}</td>
      <td>{$attempt.amount_minor|intval} {$attempt.currency|escape:'html':'UTF-8'}</td><td>{$attempt.state|escape:'html':'UTF-8'}</td>
      <td>{$attempt.receipt|escape:'html':'UTF-8'}</td><td>{$attempt.reason|escape:'html':'UTF-8'}</td><td>{$attempt.created_at|escape:'html':'UTF-8'}</td>
    </tr>{foreachelse}<tr><td colspan="8">{l s='No payment attempts yet.' mod='kopokopo'}</td></tr>{/foreach}</tbody>
  </table></div>
</div>
