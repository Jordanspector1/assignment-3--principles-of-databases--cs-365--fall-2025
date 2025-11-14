<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

// read which form was sent
$submitted = $_POST['submitted'] ?? null;


function show_empty_table(string $label = 'Result', ?string $query = null): void {
  // headers for search/update/delete results
  $headers = [
    'firstName','lastName','username','email',
    'siteName','url','comment','createdAt','password'
  ];

  echo '<table>';

  // caption telling what empty table is for
  if ($query !== null && $query !== '') {
    echo '<caption>'
       . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
       . ': 0 results for “'
       . htmlspecialchars($query, ENT_QUOTES, 'UTF-8')
       . '”</caption>';
  } else {
    echo '<caption>'
       . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
       . ': 0 results</caption>';
  }

  echo '<thead><tr>';
  foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
  }
  echo '</tr></thead>';

  // no rows
  echo '<tbody></tbody></table>';
}

// show empty table if we didn’t get any rows
function maybe_show_empty($res, ?string $label = null, ?string $query = null): void {
  if ($res === NOTHING_FOUND) {
    show_empty_table($label ?? 'Result', $query);
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Student Passwords — Assignment 3</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* small defaults in case style.css is empty */
    body { font-family: system-ui, Arial, sans-serif; margin: 2rem; }
    fieldset { margin-bottom: 1rem; }
    table { border-collapse: collapse; margin-top: 1rem; width: 100%; }
    th, td { border: 1px solid #ddd; padding: .5rem; text-align: left; }
    #error { margin-top: .5rem; padding: .5rem; background: #fee; border: 1px solid #f99; }
    #clear { margin-bottom: 1rem; }
  </style>
</head>
<body>

  <!-- clear results button -->
  <form id="clear" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <p><input type="submit" value="Clear Results"></p>
  </form>

  <?php
  // do actions first
  if ($submitted === '1') {              // search
    $q = trim($_POST['search'] ?? '');
    if ($q === '') {
      echo "<div id='error'>Please type something to search.</div>";
    } else {
      $res = search_all($q);
      maybe_show_empty($res, 'Search', $q);
    }
  } elseif ($submitted === '2') {        // update
    $t   = $_POST['current-attribute'] ?? '';
    $nv  = $_POST['new-attribute'] ?? '';
    $pa  = $_POST['query-attribute'] ?? '';
    $pat = $_POST['pattern'] ?? '';
    if ($t === '' || $nv === '' || $pa === '' || $pat === '') {
      echo "<div id='error'>Please fill out all update fields.</div>";
    } else {
      $res = update_by_pattern($t, $nv, $pa, $pat);
      maybe_show_empty($res, 'Update', $pat);
    }
  } elseif ($submitted === '3') {        // insert
    $siteName = trim($_POST['site-name'] ?? '');
    $url      = trim($_POST['url'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $comment  = trim($_POST['comment'] ?? '');
    if ($siteName === '' || $url === '' || $email === '' || $username === '' || $password === '') {
      echo "<div id='error'>Please fill out all insert fields (comment is optional).</div>";
    } else {
      $res = insert_entry($siteName, $url, $email, $username, $password, $comment);
      // always returns a row
      maybe_show_empty($res, 'Insert', null);
    }
  } elseif ($submitted === '4') {        // delete
    $attr = $_POST['current-attribute'] ?? '';
    $pat  = $_POST['pattern'] ?? '';
    if ($attr === '' || $pat === '') {
      echo "<div id='error'>Please choose a field and a value to match.</div>";
    } else {
      $res = delete_by_pattern($attr, $pat);
      maybe_show_empty($res, 'Delete', $pat);
    }
  }
  ?>

  <!-- search -->
  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <fieldset>
      <legend>Search</legend>
      <input type="text" name="search" autofocus required>
      <input type="hidden" name="submitted" value="1">
      <p><input type="submit" value="search"></p>
    </fieldset>
  </form>

  <!-- update -->
  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <fieldset>
      <legend>Update</legend>
      UPDATE SET
      <select name="current-attribute" id="current-attribute">
        <option value="user.username">user.username</option>
        <option value="user.email">user.email</option>
        <option value="user.firstName">user.firstName</option>
        <option value="user.lastName">user.lastName</option>
        <option value="site.name">site.name</option>
        <option value="site.url">site.url</option>
        <option value="credential.comment">credential.comment</option>
      </select>
      = <input type="text" name="new-attribute" required> WHERE
      <select name="query-attribute" id="query-attribute">
        <option value="user.username">user.username</option>
        <option value="user.email">user.email</option>
        <option value="user.firstName">user.firstName</option>
        <option value="user.lastName">user.lastName</option>
        <option value="site.name">site.name</option>
        <option value="site.url">site.url</option>
        <option value="credential.comment">credential.comment</option>
      </select>
      LIKE <input type="text" name="pattern" placeholder="%text%" required>
      <input type="hidden" name="submitted" value="2">
      <p><input type="submit" value="update"></p>
    </fieldset>
  </form>

  <!-- insert -->
  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <fieldset>
      <legend>Insert</legend>
      <p>
        <input type="text" name="site-name"  placeholder="site name" required>
        <input type="url"  name="url"        placeholder="https://example.com" required>
      </p>
      <p>
        <input type="email" name="email"     placeholder="email" required>
        <input type="text"  name="username"  placeholder="username" required>
      </p>
      <p>
        <input type="text"  name="password"  placeholder="password" required>
      </p>
      <p>
        <textarea name="comment" rows="3" placeholder="comment (optional)"></textarea>
      </p>
      <input type="hidden" name="submitted" value="3">
      <p><input type="submit" value="insert"></p>
    </fieldset>
  </form>

  <!-- delete -->
  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <fieldset>
      <legend>Delete</legend>
      DELETE credentials WHERE
      <select name="current-attribute" id="del-attr">
        <option value="user.username">user.username</option>
        <option value="user.email">user.email</option>
        <option value="user.firstName">user.firstName</option>
        <option value="user.lastName">user.lastName</option>
        <option value="site.name">site.name</option>
        <option value="site.url">site.url</option>
        <option value="credential.comment">credential.comment</option>
      </select>
      LIKE <input type="text" name="pattern" placeholder="%text%" required>
      <input type="hidden" name="submitted" value="4">
      <p><input type="submit" value="delete"></p>
    </fieldset>
  </form>

</body>
</html>
