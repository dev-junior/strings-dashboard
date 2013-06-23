<style>
  #new-key textarea.key {
    height:80px;
  }
</style>
<div id="new-key">
<ul id="notice"></ul>
<form class="ajax loading2" method="post" action="<?php echo $this->here . '.json'; ?>">
<fieldset>
  <legend>Name</legend>
  <input type="text" name="data[UserKey][name]" placeholder="ex: laptop" />
</fieldset>
<fieldset id="public-key">
  <legend>Public Key</legend>
  <div class="input">
    <input type="radio" id="supply-key" name="key-type" checked/>
    <label for="supply-key">I already have a key</label>
  </div>
  <div class="input">
    <input type="radio" id="generate-key" name="key-type" />
    <label for="generate-key">Generate me a key-pair</label>
    <div class="loading white" style="visibility:hidden;"></div>
  </div>
  <div class="input">
    <textarea id="public-key-value" class="key" name="data[UserKey][public_key]" placeholder="public key"></textarea>
  </div>
</fieldset>
<fieldset id="private-key" style="display:none;">
  <legend>Private Key</legend>
  <textarea id="private-key-value" class="key" disabled></textarea>
</fieldset>
<div class="submit">
  <a class="cta primary submit">Add Key</a>
  <a class="cta">Cancel</a>
</div>
</form>
</div>
<script>
  $('#supply-key').on('click',function(){
    $('#public-key-value').val("");
  });
  $('#generate-key').on('click',function(){
    var container = $(this).parent('div');
    var publicKeyTA = $('#public-key-value');
    var privateKeyFS = $('#private-key');
    var privateKeyTA = $('#private-key-value');
    var generateKeySpinner = container.find('.loading');
    generateKeySpinner.css('visibility','visible');
    publicKeyTA.val("");
    $.ajax({
      type: 'post',
      url: '/UserKeys/generateKeyPair',
      dataType: 'json',
      success: function(response) {
       publicKeyTA.val(response.publicKey);
       privateKeyTA.val(response.privateKey);
       privateKeyFS.css('display','block');
       generateKeySpinner.css('visibility','hidden');
       strings.notifications.alert('Your keypair has been generated successfully. Please save your private key.');
      }
    });
  }); 
</script>
