<?php
require_once __DIR__ . '/config.php';

if (!defined('NOTHING_FOUND')) define('NOTHING_FOUND', -1);

// make a DB connection
function get_pdo(): PDO {
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // set AES
  $pdo->exec("SET block_encryption_mode = " . quote_sql('' . AES_MODE . ''));
  $pdo->exec("SET @key_str = UNHEX(SHA2(" . quote_sql(AES_KEY_PASSPHRASE) . ", 256))");
  return $pdo;
}

// escape small sql pieces safely
function quote_sql(string $v): string {
  static $tmp;
  if (!$tmp) {
    $tmp = new PDO('mysql:host=localhost;charset=utf8mb4', DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
  }
  return $tmp->quote($v);
}

// tiny html escape
function h(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// make an HTML table from rows
function render_table(array $rows) {
  if (!$rows) return NOTHING_FOUND;

  echo "<table>\n<thead><tr>";
  foreach (array_keys($rows[0]) as $col) {
    echo '<th>' . h($col) . '</th>';
  }
  echo "</tr></thead>\n<tbody>";
  foreach ($rows as $r) {
    echo "<tr>";
    foreach ($r as $v) {
      echo "<td>" . h((string)$v) . "</td>";
    }
    echo "</tr>\n";
  }
  echo "</tbody></table>";
  return true;
}

// limit form fields to real column names so users cant inject fake ones
function map_attr(string $attr): ?string {
  $map = [
    'user.username'      => 'u.username',
    'user.email'         => 'u.email',
    'user.firstName'     => 'u.firstName',
    'user.lastName'      => 'u.lastName',
    'site.name'          => 's.siteName',
    'site.url'           => 's.url',
    'credential.comment' => 'c.comment',
  ];
  return $map[$attr] ?? null;
}

/* search */
function search_all(string $needle) {
  $pdo = get_pdo();
  $q = '%' . $needle . '%';

  $sql = "
    SELECT
      u.firstName, u.lastName, u.username, u.email,
      s.siteName, s.url,
      c.comment, c.createdAt,
      CAST(AES_DECRYPT(c.passwordCipher, @key_str) AS CHAR) AS password
    FROM credential c
    JOIN userAccount u ON u.userId = c.userId
    JOIN site s        ON s.siteId = c.siteId
    WHERE u.firstName LIKE :q
       OR u.lastName  LIKE :q
       OR u.username  LIKE :q
       OR u.email     LIKE :q
       OR s.siteName  LIKE :q
       OR s.url       LIKE :q
       OR c.comment   LIKE :q
    ORDER BY u.username, s.siteName, c.createdAt DESC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':q' => $q]);
  $rows = $stmt->fetchAll();
  return render_table($rows);
}

/* insert */
function insert_entry(
  string $siteName,
  string $url,
  string $email,
  string $username,
  string $password,
  string $comment
) {
  $pdo = get_pdo();
  $pdo->beginTransaction();

  // find or create user
  $stmt = $pdo->prepare("
    SELECT userId
    FROM userAccount
    WHERE email = :email OR username = :username
  ");
  $stmt->execute([':email' => $email, ':username' => $username]);
  $uid = $stmt->fetchColumn();

  if (!$uid) {
    $stmt = $pdo->prepare("
      INSERT INTO userAccount(firstName, lastName, username, email)
      VALUES('', '', :username, :email)
    ");
    $stmt->execute([':username' => $username, ':email' => $email]);
    $uid = (int)$pdo->lastInsertId();
  }

  // find or create site
  $stmt = $pdo->prepare("
    SELECT siteId
    FROM site
    WHERE url = :url OR siteName = :name
  ");
  $stmt->execute([':url' => $url, ':name' => $siteName]);
  $sid = $stmt->fetchColumn();

  if (!$sid) {
    $stmt = $pdo->prepare("
      INSERT INTO site(siteName, url)
      VALUES(:name, :url)
    ");
    $stmt->execute([':name' => $siteName, ':url' => $url]);
    $sid = (int)$pdo->lastInsertId();
  }

  // add credential
  $sql = "
    INSERT INTO credential(userId, siteId, passwordCipher, comment)
    VALUES(:uid, :sid, AES_ENCRYPT(:pw, @key_str), :comment)
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':uid'     => $uid,
    ':sid'     => $sid,
    ':pw'      => $password,
    ':comment' => $comment,
  ]);

  $credId = (int)$pdo->lastInsertId();
  $pdo->commit();

  // show what we inserted
  $stmt = $pdo->prepare("
    SELECT u.username, u.email, s.siteName, s.url, c.comment, c.createdAt
    FROM credential c
    JOIN userAccount u ON u.userId = c.userId
    JOIN site s        ON s.siteId = c.siteId
    WHERE c.credId = :id
  ");
  $stmt->execute([':id' => $credId]);
  return render_table($stmt->fetchAll());
}

  /* update target column where pattern on any column */
function update_by_pattern(
  string $targetAttr,
  string $newValue,
  string $patternAttr,
  string $pattern
) {
  $tcol = map_attr($targetAttr);
  $pcol = map_attr($patternAttr);
  if (!$tcol || !$pcol) {
    echo "<div id='error'>Bad field name.</div>";
    return NOTHING_FOUND;
  }

  $pdo = get_pdo();
  $sql = "
    UPDATE credential c
    JOIN userAccount u ON u.userId = c.userId
    JOIN site s        ON s.siteId = c.siteId
    SET $tcol = :val
    WHERE $pcol LIKE :pat
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':val' => $newValue, ':pat' => '%'.$pattern.'%']);

  // show affected rows
  $sql2 = "
    SELECT u.username, u.email, s.siteName, s.url, c.comment, c.createdAt
    FROM credential c
    JOIN userAccount u ON u.userId = c.userId
    JOIN site s        ON s.siteId = c.siteId
    WHERE $tcol = :val
  ";
  $stmt2 = $pdo->prepare($sql2);
  $stmt2->execute([':val' => $newValue]);
  return render_table($stmt2->fetchAll());
}

  /* delete by pattern on any column */
function delete_by_pattern(string $attr, string $pattern) {
  $col = map_attr($attr);
  if (!$col) {
    echo "<div id='error'>Bad field name.</div>";
    return NOTHING_FOUND;
  }

  $pdo = get_pdo();

  // show rows that are about to be deleted
  $preview = $pdo->prepare("
    SELECT c.credId, u.username, s.siteName, s.url, c.comment
    FROM credential c
    JOIN userAccount u ON u.userId = c.userId
    JOIN site s        ON s.siteId = c.siteId
    WHERE $col LIKE :pat
  ");
  $preview->execute([':pat' => '%'.$pattern.'%']);
  $rows = $preview->fetchAll();

  // delete
  $del = $pdo->prepare("
    DELETE c FROM credential c
    JOIN userAccount u ON u.userId = c.userId
    JOIN site s        ON s.siteId = c.siteId
    WHERE $col LIKE :pat
  ");
  $del->execute([':pat' => '%'.$pattern.'%']);

  if (!$rows) return NOTHING_FOUND;
  return render_table($rows);
}
