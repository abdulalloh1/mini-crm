<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Auth\Auth;
use App\Auth\LoginService;
use App\Http\Response;
use App\Http\View;
use App\Security\Csrf;
use App\Middleware\RequireAuth;
use App\Leads\LeadRepository;
use App\Leads\NoteRepository;

Auth::start();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/' && $method === 'GET') {
    if (\App\Auth\Auth::check()) {
        \App\Http\Response::redirect('/me');
    }
    \App\Http\Response::redirect('/login');
}

if ($path === '/health') {
    Response::json(['ok' => true, 'time' => date('c')]);
}

if ($path === '/db-check') {
    try {
        $pdo = \App\Database\PdoFactory::make();
        $dbName = $pdo->query("SELECT current_database()")->fetchColumn();
        Response::json(['ok' => true, 'db' => $dbName]);
    } catch (Throwable $e) {
        Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($path === '/login' && $method === 'GET') {
    if (Auth::check()) {
        Response::redirect('/me');
    }

    $csrf = Csrf::token();

    $html = '
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Login</title>
          <style>
            body{font-family:system-ui,Arial;max-width:420px;margin:60px auto;padding:0 16px}
            .card{border:1px solid #ddd;border-radius:12px;padding:18px}
            label{display:block;margin-top:12px;font-size:14px}
            input{width:100%;padding:10px;border:1px solid #ccc;border-radius:10px;margin-top:6px}
            button{margin-top:14px;padding:10px 14px;border-radius:10px;border:0;cursor:pointer}
            .hint{color:#666;font-size:13px;margin-top:10px}
          </style>
        </head>
        <body>
          <div class="card">
            <h2>Mini CRM — Login</h2>
        
            <form method="POST" action="/login">
              <input type="hidden" name="_csrf" value="' . View::e($csrf) . '">
        
              <label>Email</label>
              <input name="email" type="email" placeholder="admin@crm.local" required>
        
              <label>Password</label>
              <input name="password" type="password" placeholder="admin123" required>
        
              <button type="submit">Sign in</button>
            </form>
        
            <div class="hint">Tip: admin@crm.local / admin123</div>
          </div>
        </body>
        </html>
    ';

    View::html($html);
}

if ($path === '/login' && $method === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::json(['error' => 'Invalid CSRF token'], 419);
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        Response::json(['error' => 'Email and password required'], 422);
    }

    $user = LoginService::attempt($email, $password);

    if (!$user) {
        Response::json(['error' => 'Invalid credentials'], 401);
    }

    Auth::login($user);

    Response::redirect('/me');
}

if ($path === '/logout' && $method === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::json(['error' => 'Invalid CSRF token'], 419);
    }

    Auth::logout();
    Response::redirect('/login');
}

if ($path === '/me' && $method === 'GET') {
    RequireAuth::handle();

    $user = Auth::user();

    $html = '
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <style>
    body{font-family:system-ui,Arial;max-width:900px;margin:40px auto;padding:0 16px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:12px}
    .card{border:1px solid #ddd;border-radius:12px;padding:16px;margin-top:16px}
    a,button{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #ccc;background:#fff;text-decoration:none;color:#111;cursor:pointer}
    .muted{color:#666}
    form{margin:0}
  </style>
</head>
<body>
  <div class="top">
    <div>
      <h2>Mini CRM — Dashboard</h2>
      <div class="muted">Logged in as: ' . \App\Http\View::e($user['email']) . ' (role: ' . \App\Http\View::e($user['role']) . ')</div>
    </div>

    <form method="POST" action="/logout">
      <input type="hidden" name="_csrf" value="' . \App\Http\View::e(\App\Security\Csrf::token()) . '">
      <button type="submit">Logout</button>
    </form>
  </div>

  <div class="card">
    <h3>Navigation</h3>
    <p><a href="/leads">Leads</a></p>
  </div>
</body>
</html>';

    \App\Http\View::html($html);
}

if ($path === '/leads' && $method === 'GET') {
    RequireAuth::handle();

    $repo = new LeadRepository();

    // читаем query параметры: ?status=new&page=2
    $status = $_GET['status'] ?? null;
    $status = is_string($status) ? trim($status) : null;
    if ($status === '') $status = null;

    $allowedStatuses = ['new', 'in_progress', 'won', 'lost'];
    if ($status !== null && !in_array($status, $allowedStatuses, true)) {
        $status = null; // если мусор — игнор
    }

    $page = (int)($_GET['page'] ?? 1);
    if ($page < 1) $page = 1;

    $limit = 2;
    $total = $repo->countAll($status);
    $pages = (int)ceil($total / $limit);
    if ($pages < 1) $pages = 1;
    if ($page > $pages) $page = $pages;

    $offset = ($page - 1) * $limit;

    $leads = $repo->listWithStatsPaginated($status, $limit, $offset);

    $noteRepo = new NoteRepository();

    $rows = '';
    foreach ($leads as $lead) {
        $count = (int)$lead['notes_count'];

        $lastText = '';
        if (!empty($lead['last_note_text'])) {
            $lastText = $lead['last_note_author_email'] . ': ' . $lead['last_note_text'];
        }

        $rows .= '<tr>
          <td>' . View::e((string)$lead['id']) . '</td>
          <td>
              <a href="/leads/' . \App\Http\View::e((string)$lead['id']) . '">
                ' . \App\Http\View::e($lead['name']) . '
              </a>
            </td>
          <td>' . View::e($lead['phone'] ?? '') . '</td>
          <td>' . View::e($lead['status']) . '</td>
          <td>' . View::e((string)$count) . '</td>
            <td>' . View::e($lastText) . '</td>
          <td>' . View::e((string)$lead['created_at']) . '</td>
        </tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="5" class="muted">No leads yet</td></tr>';
    }

    $queryBase = '';
    if ($status) {
        $queryBase = 'status=' . urlencode($status) . '&';
    }

    $prevLink = $page > 1 ? ('/leads?' . $queryBase . 'page=' . ($page - 1)) : null;
    $nextLink = $page < $pages ? ('/leads?' . $queryBase . 'page=' . ($page + 1)) : null;

    $pagerHtml = '<div style="display:flex; gap:10px; align-items:center; margin-top:12px;">';
    $pagerHtml .= $prevLink ? '<a href="' . \App\Http\View::e($prevLink) . '">Prev</a>' : '<span style="color:#999">Prev</span>';
    $pagerHtml .= '<span style="color:#666">Page ' . $page . ' / ' . $pages . '</span>';
    $pagerHtml .= $nextLink ? '<a href="' . \App\Http\View::e($nextLink) . '">Next</a>' : '<span style="color:#999">Next</span>';
    $pagerHtml .= '</div>';

    $filterHtml = '
<form method="GET" action="/leads" style="display:flex; gap:10px; align-items:center;">
  <label class="muted">Status:</label>
  <select name="status" onchange="this.form.submit()">
    <option value="">All</option>
    <option value="new" ' . ($status==='new'?'selected':'') . '>new</option>
    <option value="in_progress" ' . ($status==='in_progress'?'selected':'') . '>in_progress</option>
    <option value="won" ' . ($status==='won'?'selected':'') . '>won</option>
    <option value="lost" ' . ($status==='lost'?'selected':'') . '>lost</option>
  </select>
  <noscript><button type="submit">Apply</button></noscript>
</form>
';

    $html = '
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Leads</title>
          <style>
            body{font-family:system-ui,Arial;max-width:900px;margin:40px auto;padding:0 16px}
            .top{display:flex;justify-content:space-between;align-items:center;gap:12px}
            .card{border:1px solid #ddd;border-radius:12px;padding:16px;margin-top:16px}
            a,button{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #ccc;background:#fff;text-decoration:none;color:#111;cursor:pointer}
            table{width:100%;border-collapse:collapse;margin-top:12px}
            th,td{border-bottom:1px solid #eee;padding:10px;text-align:left}
            .muted{color:#666}
          </style>
        </head>
        <body>
          <div class="top">
            <h2>Leads</h2>
            <div>
              <a href="/leads/create">+ Add lead</a>
              <a href="/me">Back</a>
            </div>
            
            ' . $filterHtml . '
          </div>
        
          <div class="card">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Phone</th>
                  <th>Status</th>
                  <th>Count</th>
                  <th>Last text</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                ' . $rows . '
              </tbody>
            </table>
            
            ' . $pagerHtml . '
          </div>
        </body>
        </html>
    ';

    \App\Http\View::html($html);
}

if ($path === '/leads/create' && $method === 'GET') {
    RequireAuth::handle();

    $csrf = \App\Security\Csrf::token();

    $html = '
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Create lead</title>
          <style>
            body{font-family:system-ui,Arial;max-width:600px;margin:40px auto;padding:0 16px}
            .card{border:1px solid #ddd;border-radius:12px;padding:16px;margin-top:16px}
            label{display:block;margin-top:12px}
            input,select{width:100%;padding:10px;border:1px solid #ccc;border-radius:10px;margin-top:6px}
            button,a{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #ccc;background:#fff;text-decoration:none;color:#111;cursor:pointer;margin-top:14px}
            .row{display:flex;gap:10px;align-items:center}
          </style>
        </head>
        <body>
          <h2>Create lead</h2>
        
          <div class="card">
            <form method="POST" action="/leads/create">
              <input type="hidden" name="_csrf" value="' . \App\Http\View::e($csrf) . '">
        
              <label>Name *</label>
              <input name="name" required>
        
              <label>Phone</label>
              <input name="phone" placeholder="+998 ...">
        
              <label>Status</label>
              <select name="status">
                <option value="new">new</option>
                <option value="in_progress">in_progress</option>
                <option value="won">won</option>
                <option value="lost">lost</option>
              </select>
        
              <div class="row">
                <button type="submit">Save</button>
                <a href="/leads">Cancel</a>
              </div>
            </form>
          </div>
        </body>
        </html>
    ';

    \App\Http\View::html($html);
}

if ($path === '/leads/create' && $method === 'POST') {
    RequireAuth::handle();

    if (!\App\Security\Csrf::verify($_POST['_csrf'] ?? null)) {
        \App\Http\Response::json(['error' => 'Invalid CSRF token'], 419);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $status = (string)($_POST['status'] ?? 'new');

    if ($name === '') {
        \App\Http\Response::json(['error' => 'Name is required'], 422);
    }

    $allowedStatuses = ['new', 'in_progress', 'won', 'lost'];
    if (!in_array($status, $allowedStatuses, true)) {
        \App\Http\Response::json(['error' => 'Invalid status'], 422);
    }

    $repo = new LeadRepository();
    $user = \App\Auth\Auth::user();

    $repo->create(
        name: $name,
        phone: $phone === '' ? null : $phone,
        status: $status,
        createdBy: (int)$user['id']
    );

    \App\Http\Response::redirect('/leads');
}

if ($method === 'GET' && preg_match('#^/leads/(\d+)$#', $path, $m)) {
    RequireAuth::handle();

    $leadId = (int)$m[1];

    $leadRepo = new LeadRepository();
    $noteRepo = new NoteRepository();

    $lead = $leadRepo->findById($leadId);
    if (!$lead) {
        \App\Http\Response::json(['error' => 'Lead not found'], 404);
    }

    $notes = $noteRepo->listByLead($leadId);

    $notesHtml = '';
    foreach ($notes as $n) {
        $notesHtml .= '<div style="border-top:1px solid #eee;padding:10px 0">
          <div style="font-size:13px;color:#666">
            ' . \App\Http\View::e($n['author_email']) . ' • ' . \App\Http\View::e((string)$n['created_at']) . '
          </div>
          <div>' . \App\Http\View::e($n['text']) . '</div>
        </div>';
    }
    if ($notesHtml === '') {
        $notesHtml = '<div style="color:#666">No notes yet</div>';
    }

    $csrf = \App\Security\Csrf::token();
    $user = \App\Auth\Auth::user();
    $isAdmin = ($user['role'] ?? '') === 'admin';

    $deleteHtml = '';
    if ($isAdmin) {
        $deleteHtml = '
      <form method="POST" action="/leads/' . \App\Http\View::e((string)$leadId) . '/delete" style="display:inline">
        <input type="hidden" name="_csrf" value="' . \App\Http\View::e($csrf) . '">
        <button type="submit" onclick="return confirm(\'Delete lead?\')">Delete</button>
      </form>
    ';
    }

    $html = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lead #' . \App\Http\View::e((string)$lead['id']) . '</title>
  <style>
    body{font-family:system-ui,Arial;max-width:900px;margin:40px auto;padding:0 16px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:12px}
    .card{border:1px solid #ddd;border-radius:12px;padding:16px;margin-top:16px}
    a,button{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #ccc;background:#fff;text-decoration:none;color:#111;cursor:pointer}
    input,textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:10px;margin-top:6px}
    textarea{min-height:90px;resize:vertical}
    .muted{color:#666}
  </style>
</head>
<body>
    <div class="top">
    <h2>Lead #' . \App\Http\View::e((string)$lead['id']) . '</h2>
    <div style="display:flex; gap:10px; align-items:center;">
      <a href="/leads">Back</a>

      <a href="/leads/' . \App\Http\View::e((string)$leadId) . '/edit">Edit</a>

        ' . $deleteHtml . '
    </div>
  </div>

  <div class="card">
    <div><b>Name:</b> ' . \App\Http\View::e($lead['name']) . '</div>
    <div><b>Phone:</b> ' . \App\Http\View::e($lead['phone'] ?? '') . '</div>
    <div><b>Status:</b> ' . \App\Http\View::e($lead['status']) . '</div>
    <div class="muted"><b>Created:</b> ' . \App\Http\View::e((string)$lead['created_at']) . '</div>
  </div>

  <div class="card">
    <h3>Add note</h3>
    <form method="POST" action="/leads/' . \App\Http\View::e((string)$leadId) . '/notes">
      <input type="hidden" name="_csrf" value="' . \App\Http\View::e($csrf) . '">
      <textarea name="text" placeholder="Write note..." required></textarea>
      <button type="submit">Save note</button>
    </form>
  </div>

  <div class="card">
    <h3>Notes</h3>
    ' . $notesHtml . '
  </div>
</body>
</html>';

    \App\Http\View::html($html);
}

if ($method === 'POST' && preg_match('#^/leads/(\d+)/notes$#', $path, $m)) {
    RequireAuth::handle();

    if (!\App\Security\Csrf::verify($_POST['_csrf'] ?? null)) {
        \App\Http\Response::json(['error' => 'Invalid CSRF token'], 419);
    }

    $leadId = (int)$m[1];
    $text = trim((string)($_POST['text'] ?? ''));

    if ($text === '') {
        \App\Http\Response::json(['error' => 'Text is required'], 422);
    }

    $leadRepo = new LeadRepository();
    if (!$leadRepo->findById($leadId)) {
        \App\Http\Response::json(['error' => 'Lead not found'], 404);
    }

    $user = \App\Auth\Auth::user();
    $noteRepo = new NoteRepository();
    $noteRepo->create($leadId, (int)$user['id'], $text);

    \App\Http\Response::redirect("/leads/{$leadId}");
}

if ($method === 'GET' && preg_match('#^/leads/(\d+)/edit$#', $path, $m)) {
    RequireAuth::handle();

    $leadId = (int)$m[1];
    $repo = new LeadRepository();
    $lead = $repo->findById($leadId);

    if (!$lead) {
        \App\Http\Response::json(['error' => 'Lead not found'], 404);
    }

    $csrf = \App\Security\Csrf::token();

    $html = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit lead</title>
  <style>
    body{font-family:system-ui,Arial;max-width:600px;margin:40px auto;padding:0 16px}
    .card{border:1px solid #ddd;border-radius:12px;padding:16px;margin-top:16px}
    label{display:block;margin-top:12px}
    input,select{width:100%;padding:10px;border:1px solid #ccc;border-radius:10px;margin-top:6px}
    button,a{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #ccc;background:#fff;text-decoration:none;color:#111;cursor:pointer;margin-top:14px}
    .row{display:flex;gap:10px;align-items:center}
  </style>
</head>
<body>
  <h2>Edit lead #' . \App\Http\View::e((string)$leadId) . '</h2>

  <div class="card">
    <form method="POST" action="/leads/' . \App\Http\View::e((string)$leadId) . '/edit">
      <input type="hidden" name="_csrf" value="' . \App\Http\View::e($csrf) . '">

      <label>Name *</label>
      <input name="name" required value="' . \App\Http\View::e($lead['name']) . '">

      <label>Phone</label>
      <input name="phone" value="' . \App\Http\View::e($lead['phone'] ?? '') . '">

      <label>Status</label>
      <select name="status">
        <option value="new" ' . ($lead['status']==='new'?'selected':'') . '>new</option>
        <option value="in_progress" ' . ($lead['status']==='in_progress'?'selected':'') . '>in_progress</option>
        <option value="won" ' . ($lead['status']==='won'?'selected':'') . '>won</option>
        <option value="lost" ' . ($lead['status']==='lost'?'selected':'') . '>lost</option>
      </select>

      <div class="row">
        <button type="submit">Save</button>
        <a href="/leads/' . \App\Http\View::e((string)$leadId) . '">Cancel</a>
      </div>
    </form>
  </div>
</body>
</html>';

    \App\Http\View::html($html);
}

if ($method === 'POST' && preg_match('#^/leads/(\d+)/edit$#', $path, $m)) {
    RequireAuth::handle();

    if (!\App\Security\Csrf::verify($_POST['_csrf'] ?? null)) {
        \App\Http\Response::json(['error' => 'Invalid CSRF token'], 419);
    }

    $leadId = (int)$m[1];

    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $status = (string)($_POST['status'] ?? 'new');

    if ($name === '') {
        \App\Http\Response::json(['error' => 'Name is required'], 422);
    }

    $allowedStatuses = ['new', 'in_progress', 'won', 'lost'];
    if (!in_array($status, $allowedStatuses, true)) {
        \App\Http\Response::json(['error' => 'Invalid status'], 422);
    }

    $repo = new LeadRepository();
    if (!$repo->findById($leadId)) {
        \App\Http\Response::json(['error' => 'Lead not found'], 404);
    }

    $repo->update($leadId, $name, $phone === '' ? null : $phone, $status);

    \App\Http\Response::redirect("/leads/{$leadId}");
}

if ($method === 'POST' && preg_match('#^/leads/(\d+)/delete$#', $path, $m)) {
    RequireAuth::handle();

    if (!\App\Security\Csrf::verify($_POST['_csrf'] ?? null)) {
        \App\Http\Response::json(['error' => 'Invalid CSRF token'], 419);
    }

    $user = \App\Auth\Auth::user();
    if (($user['role'] ?? '') !== 'admin') {
        \App\Http\Response::json(['error' => 'Forbidden'], 403);
    }

    $leadId = (int)$m[1];

    $repo = new LeadRepository();
    if (!$repo->findById($leadId)) {
        \App\Http\Response::json(['error' => 'Lead not found'], 404);
    }

    $repo->delete($leadId);

    \App\Http\Response::redirect('/leads');
}

Response::json(['error' => 'Not Found'], 404);