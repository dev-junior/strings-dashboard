<div>
  <a id="logo">Strings</a>
  <ul class="action-menu" id="menu" data-width="140">
    <li><img src="/img/avatar.png"/> <?php echo $this->Session->read('Auth.User.full_name'); ?></li>
    <span>
      <a class="modal" data-src="/users/mySettings.json" data-title="My Settings">Settings</a>
      <a href="/logout">Sign Out</a>
    </span>
  </ul>
</div>
