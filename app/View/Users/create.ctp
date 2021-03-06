<div id="create-user">
  <ul id="notice">
  </ul>
  <form class="ajax" method="post" action="<?php echo $_SERVER['REQUEST_URI'] . ".json"; ?>" >
    <fieldset>
      <legend>Name</legend>
      <input type="text" placeholder="first name" name="data[User][first_name]" />
      <input type="text" placeholder="last name" name="data[User][last_name]" /> 
    </fieldset>
	<fieldset>
      <legend>Account</legend>
      <input type="text" placeholder="username" name="data[User][name]" />
      <input type="email" placeholder="email address" name="data[User][email]" />
      <span class="small">*Strings will email this user instructions for setting his or her initial password and logging in.</span>
    </fieldset>
    <fieldset>
      <legend>Control Panel Privileges</legend>
      <div class="input">
        <input type="radio" id="is_admin" name="data[User][is_admin]" value="1" />
        <label for="is_admin">Administrator</label>
      </div>
      <div class="input">
        <input type="radio" id="is_user" name="data[User][is_admin]" value="0" checked/>
        <label for="is_user" >User</label>
      </div>
    </fieldset>
    <div class="submit">
      <a class="cta primary submit">Confirm</a>
      <a class="cta">Cancel</a>
    </div>
  </form>          
</div>
