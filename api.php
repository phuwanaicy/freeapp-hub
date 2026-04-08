<?php
/**
 * FreeApp HUB — API Proxy + IP Session
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-device-id, x-license-key, x-csrf-token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('SECRET_KEY', 'OTP24HRHUB_PROTECT');
define('API_BASE',   'https://otp24hr.com/api/v1/tools/api');
define('ORIGIN',     'chrome-extension://bijigadbjcphkmpdmkhbdeccbakfckkn');
define('LOG_FILE',   __DIR__ . '/log.txt');
define('MAX_USES',   5);

// ─── Get real client IP ───────────────────────────────────────────────────────
function get_client_ip() {
    foreach (array('HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR') as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ─── IP Session helpers (log.txt = JSON lines) ────────────────────────────────
function load_sessions() {
    if (!file_exists(LOG_FILE)) return array();
    $sessions = array();
    foreach (file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = json_decode($line, true);
        if ($row && isset($row['ip'])) $sessions[$row['ip']] = $row;
    }
    return $sessions;
}

function save_sessions($sessions) {
    $lines = array();
    foreach ($sessions as $row) $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE);
    file_put_contents(LOG_FILE, implode("\n", $lines) . "\n", LOCK_EX);
}

function get_session($ip) {
    $sessions = load_sessions();
    return isset($sessions[$ip]) ? $sessions[$ip] : null;
}

function set_session($ip, $license_key, $device_id, $ua, $used = 0) {
    $sessions = load_sessions();
    $sessions[$ip] = array(
        'ip'          => $ip,
        'license_key' => $license_key,
        'device_id'   => $device_id,
        'ua'          => $ua,
        'used'        => $used,
        'updated'     => time(),
    );
    save_sessions($sessions);
}

function increment_used($ip) {
    $sessions = load_sessions();
    if (isset($sessions[$ip])) {
        $sessions[$ip]['used']    = ($sessions[$ip]['used'] ?? 0) + 1;
        $sessions[$ip]['updated'] = time();
        save_sessions($sessions);
        return $sessions[$ip]['used'];
    }
    return 0;
}

function delete_session($ip) {
    $sessions = load_sessions();
    unset($sessions[$ip]);
    save_sessions($sessions);
}

// ─── Is-duplicate detector (ทั้ง Thai + English) ─────────────────────────────
function is_duplicate_msg($msg) {
    $keywords = array(
        'already', 'duplicate', 'used', 'demo',
        'เคย', 'ซ้ำ', 'ไปแล้ว', 'ทดลอง', 'รับสิทธิ์',
    );
    foreach ($keywords as $kw) {
        if (stripos($msg, $kw) !== false) return true;
    }
    return false;
}

function rnd($arr) { return $arr[array_rand($arr)]; }

// ─── Fingerprint Generator ────────────────────────────────────────────────────
function generate_fingerprint() {
    $major = mt_rand(115, 124);
    $ver   = $major . '.0.' . mt_rand(0,6000) . '.' . mt_rand(0,150);
    $mac_v = rnd(array('10_15_7','11_6_0','12_6_0','13_4_0','14_3_1'));
    $platforms = array(
        array('p'=>'Win32',       'ua'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/'.$ver.' Safari/537.36'),
        array('p'=>'MacIntel',    'ua'=>'Mozilla/5.0 (Macintosh; Intel Mac OS X '.$mac_v.') AppleWebKit/537.36 (KHTML, like Gecko) Chrome/'.$ver.' Safari/537.36'),
        array('p'=>'Linux x86_64','ua'=>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/'.$ver.' Safari/537.36'),
    );
    $os   = rnd($platforms);
    $cpu  = rnd(array(2,4,6,8,10,12,16,24,32,64));
    $ram  = rnd(array(4,8,12,16,24,32,64,128));
    $res  = rnd(array('1280x720','1366x768','1440x900','1536x864','1920x1080','2560x1440','3840x2160'));
    $lang = rnd(array('th-TH','en-US','en-GB','zh-CN','ja-JP'));
    $ts   = time() - mt_rand(3600, 604800);
    $raw  = 'OTP|'.$os['p'].'|'.$cpu.'|'.$ram.'|'.$res.'|Asia/Bangkok|'.$lang.'|'.$ts;
    return array('device_id' => rtrim(strtr(base64_encode($raw),'+/','-_'),'='), 'ua' => $os['ua']);
}

// ─── XOR Decode ───────────────────────────────────────────────────────────────
function xor_decode($encoded, $key) {
    $bin  = base64_decode($encoded);
    if (!$bin) return null;
    $out  = '';
    $klen = strlen($key);
    for ($i = 0; $i < strlen($bin); $i++) $out .= chr(ord($bin[$i]) ^ ord($key[$i % $klen]));
    return $out;
}

// ─── HTTP Client ──────────────────────────────────────────────────────────────
function api_request($method, $url, $body=array(), $device_id='', $license_key='', $csrf='', $ua='') {
    if (!function_exists('curl_init'))
        return array('success'=>false,'message'=>'cURL not available on this server');

    $ua = $ua ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36';
    $hdrs = array(
        'User-Agent: '.$ua,
        'Origin: '.ORIGIN,
        'Accept: application/json, text/plain, */*',
        'x-device-id: '.$device_id,
        'x-license-key: '.$license_key,
        'x-csrf-token: '.$csrf,
    );
    if ($method === 'POST') $hdrs[] = 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8';

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_HEADER         => true,
    ));
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $hsz = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if (!$raw) return array('success'=>false,'message'=>'cURL error: '.$err);

    $hdr_str  = substr($raw, 0, $hsz);
    $body_str = substr($raw, $hsz);
    if (preg_match('/x-csrf-token:\s*(.+)/i', $hdr_str, $m)) header('x-csrf-token: '.trim($m[1]));

    $data = json_decode($body_str, true);
    if (!is_array($data)) return array('success'=>false,'message'=>'Bad upstream response: '.substr($body_str,0,300));
    return $data;
}

// ─── Router ───────────────────────────────────────────────────────────────────
$action   = isset($_GET['action']) ? $_GET['action'] : '';
$input    = json_decode(@file_get_contents('php://input'), true);
if (!is_array($input)) $input = array();

$client_ip = get_client_ip();
$hdr_csrf  = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';

switch ($action) {

    // ── ตรวจสอบ session จาก IP ──────────────────────────────────────────────
    case 'check_session': {
        $sess = get_session($client_ip);
        if ($sess && !empty($sess['license_key'])) {
            $remaining = MAX_USES - intval($sess['used']);
            if ($remaining <= 0) {
                // ใช้ครบแล้ว → ลบ session ให้ VPN
                delete_session($client_ip);
                echo json_encode(array('success'=>false,'exhausted'=>true,'message'=>'ใช้ครบ '.MAX_USES.' ครั้งแล้ว'));
            } else {
                echo json_encode(array(
                    'success'     => true,
                    'license_key' => $sess['license_key'],
                    'device_id'   => $sess['device_id'],
                    'ua'          => $sess['ua'],
                    'used'        => intval($sess['used']),
                    'remaining'   => $remaining,
                ));
            }
        } else {
            echo json_encode(array('success'=>false,'message'=>'No session'));
        }
        break;
    }

    // ── DEBUG: ดู raw response จาก upstream ─────────────────────────────────
    case 'debug_demo': {
        $fp  = generate_fingerprint();
        $did = $fp['device_id'];
        $ua  = $fp['ua'];

        $res = api_request('POST', API_BASE.'?action=create_order',
                           array('pkg_id'=>'1','device_id'=>$did), $did,'','',$ua);

        // decode payload ถ้ามี
        $decoded_pay = null;
        if ($res && !empty($res['payload'])) {
            $raw = xor_decode($res['payload'], SECRET_KEY);
            $decoded_pay = $raw ? json_decode($raw, true) : $raw;
        }

        echo json_encode(array(
            'fingerprint_used' => $did,
            'raw_response'     => $res,
            'decoded_payload'  => $decoded_pay,
            'server_ip'        => gethostbyname(gethostname()),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
    }


    case 'create_demo': {
        // เช็ค session เก่าก่อน
        $sess = get_session($client_ip);
        if ($sess && !empty($sess['license_key']) && intval($sess['used']) < MAX_USES) {
            echo json_encode(array(
                'success'      => true,
                'license_key'  => $sess['license_key'],
                'device_id'    => $sess['device_id'],
                'ua'           => $sess['ua'],
                'used'         => intval($sess['used']),
                'from_session' => true,
            ));
            break;
        }

        // วนลองสุ่ม fingerprint ใหม่สูงสุด 15 รอบ
        $MAX_RETRY  = 15;
        $found_key  = null;
        $found_did  = null;
        $found_ua   = null;
        $last_msg   = 'ไม่สามารถสร้างคีย์ได้';

        for ($attempt = 0; $attempt < $MAX_RETRY; $attempt++) {
            $fp  = generate_fingerprint();
            $did = $fp['device_id'];
            $ua  = $fp['ua'];

            $res = api_request('POST', API_BASE.'?action=create_order',
                               array('pkg_id'=>'1','device_id'=>$did), $did,'','',$ua);

            if (!$res) { $last_msg = 'Network error'; continue; }

            if (!empty($res['success'])) {
                $raw = xor_decode($res['payload'], SECRET_KEY);
                $pay = $raw ? json_decode($raw, true) : null;
                $key = null;
                if ($pay) {
                    $key = isset($pay['new_key'])      ? $pay['new_key']
                         : (isset($pay['license_key']) ? $pay['license_key'] : null);
                }

                if ($key) {
                    $found_key = $key;
                    $found_did = $did;
                    $found_ua  = $ua;
                    break; // ได้ key แล้ว หยุดลอง
                }
                // success=true แต่ไม่มี key = fingerprint นี้ซ้ำ → ลองใหม่
                $last_msg = 'Fingerprint duplicate — retrying ('.$attempt.')';

            } else {
                $msg = isset($res['message']) ? $res['message'] : 'Failed';
                $last_msg = $msg;
                // ถ้าไม่ใช่ duplicate error หยุดเลย (เช่น network / server error)
                if (!is_duplicate_msg($msg)) break;
                // เป็น duplicate → ลองใหม่
            }
        }

        if ($found_key) {
            set_session($client_ip, $found_key, $found_did, $found_ua, 0);
            echo json_encode(array(
                'success'     => true,
                'license_key' => $found_key,
                'device_id'   => $found_did,
                'ua'          => $found_ua,
                'used'        => 0,
            ));
        } else {
            // ลองครบ 15 รอบแล้วยังไม่ได้ → แจ้ง user จริงๆ
            echo json_encode(array(
                'success'   => false,
                'duplicate' => true,
                'message'   => 'ไม่สามารถสร้าง Key ได้หลังลอง '.$MAX_RETRY.' ครั้ง — กรุณาเปิด VPN แล้วลองใหม่',
            ));
        }
        break;
    }

    // ── Login & get apps ──────────────────────────────────────────────────────
    case 'login': {
        $key = isset($input['license_key']) ? $input['license_key'] : '';
        $did = isset($input['device_id'])   ? $input['device_id']   : '';
        $ua  = isset($input['ua'])           ? $input['ua']          : '';

        $res = api_request('POST', API_BASE.'?action=login', array('key'=>$key), $did,$key,$hdr_csrf,$ua);
        if (!$res || empty($res['success'])) {
            echo json_encode(array('success'=>false,'message'=>isset($res['message'])?$res['message']:'Login failed'));
            break;
        }
        $pay = json_decode(xor_decode($res['payload'],SECRET_KEY),true) ?: array();

        // อัปเดต used จาก API
        $used_api = isset($pay['used_today']) ? intval($pay['used_today']) : 0;
        $sess = get_session($client_ip);
        if ($sess) {
            // sync used count จาก API
            set_session($client_ip, $key, $did, $ua, $used_api);
        }

        echo json_encode(array(
            'success'      => true,
            'apps'         => isset($pay['apps'])         ? $pay['apps']         : array(),
            'package_type' => isset($pay['package_type']) ? $pay['package_type'] : 'DEMO',
            'used_today'   => $used_api,
            'daily_limit'  => isset($pay['daily_limit'])  ? intval($pay['daily_limit'])  : MAX_USES,
            'expiry_date'  => isset($pay['expiry_date'])  ? $pay['expiry_date']  : null,
        ));
        break;
    }

    // ── Get nodes ─────────────────────────────────────────────────────────────
    case 'get_nodes': {
        $aid = isset($input['app_id'])      ? $input['app_id']      : '';
        $key = isset($input['license_key']) ? $input['license_key'] : '';
        $did = isset($input['device_id'])   ? $input['device_id']   : '';
        $ua  = isset($input['ua'])           ? $input['ua']          : '';

        $res = api_request('GET', API_BASE.'?action=get_nodes&app_id='.urlencode($aid).'&key='.urlencode($key),
                           array(),$did,$key,$hdr_csrf,$ua);
        if (!$res || empty($res['success'])) {
            echo json_encode(array('success'=>false,'message'=>isset($res['message'])?$res['message']:'No nodes'));
            break;
        }
        $nodes = json_decode(xor_decode($res['payload'],SECRET_KEY),true) ?: array();
        echo json_encode(array('success'=>true,'nodes'=>$nodes));
        break;
    }

    // ── Get cookie + นับ uses ─────────────────────────────────────────────────
    case 'get_cookie': {
        $nid = isset($input['node_id'])     ? $input['node_id']     : '';
        $key = isset($input['license_key']) ? $input['license_key'] : '';
        $did = isset($input['device_id'])   ? $input['device_id']   : '';
        $ua  = isset($input['ua'])           ? $input['ua']          : '';

        $res = api_request('GET', API_BASE.'?action=get_cookie&node_id='.urlencode($nid).'&key='.urlencode($key),
                           array(),$did,$key,$hdr_csrf,$ua);
        if (!$res || empty($res['success'])) {
            echo json_encode(array('success'=>false,'message'=>isset($res['message'])?$res['message']:'Cookie fetch failed'));
            break;
        }
        $data = json_decode(xor_decode($res['payload'],SECRET_KEY),true) ?: array();

        // นับการใช้งาน
        $new_used = increment_used($client_ip);
        $remaining = MAX_USES - $new_used;

        echo json_encode(array(
            'success'    => true,
            'cookies'    => isset($data['cookies'])    ? $data['cookies']    : array(),
            'target_url' => isset($data['target_url']) ? $data['target_url'] : '',
            'used'       => $new_used,
            'remaining'  => max(0, $remaining),
            'exhausted'  => $remaining <= 0,
        ));

        // ถ้าหมดแล้ว ลบ session ออก
        if ($remaining <= 0) delete_session($client_ip);
        break;
    }

    default:
        echo json_encode(array('success'=>false,'message'=>'Unknown action'));
}
