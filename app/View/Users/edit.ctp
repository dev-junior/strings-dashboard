<div id="edit-user">
  <ul id="notice">
  </ul>
  <form class="ajax" method="POST" action="<?php echo $_SERVER['REQUEST_URI'] . ".json"; ?>">
    <fieldset>
      <legend>Name</legend>
      <input type="text" placeholder="first name" name="data[User][first_name]" value="<?php echo $user['User']['first_name']; ?>" />
      <input type="text" placeholder="last name" name="data[User][last_name]" value="<?php echo $user['User']['last_name']; ?>" />
    </fieldset>
    <fieldset>
      <legend>Details</legend>
      <input type="email" placeholder="email address" name="data[User][email]" value="<?php echo $user['User']['email']; ?>" />
    </fieldset>
    <div class="submit">
      <a class="cta primary submit">Save</a>
      <a class="cta">Cancel</a>
    </div>
  </form>          
</div>