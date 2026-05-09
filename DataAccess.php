<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class HAMLog_DataAccess
{
    private $db;
    private $prefix;
    private $config;
    
    public function __construct()
    {
        $this->db = Typecho_Db::get();
        $this->prefix = $this->db->getPrefix();
        
        if (class_exists('HAMLog_Plugin')) {
            $this->config = HAMLog_Plugin::getConfig();
        } else {
            $this->config = $this->getDefaultConfig();
        }
    }
    
    private function getDefaultConfig() {
        return [
            'save_type' => 'local',
            'supabase_api' => '',
            'supabase_key' => '',
            'supabase_table' => '',
            'supabase_note' => '',
            'front_fields' => 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,MODE,FREQ',
            'admin_fields' => 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,MODE,FREQ,REMARK,CARD_SEND,CARD_RCV'
        ];
    }
    
    private function isSupabase()
    {
        return $this->config['save_type'] === 'supabase';
    }
    
    private function getSupabaseHeaders()
    {
        return [
            'apikey: ' . $this->config['supabase_key'],
            'Authorization: Bearer ' . $this->config['supabase_key'],
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }
    
    private function callSupabase($method, $url, $data = null)
    {
        $ch = curl_init();
        $fullUrl = rtrim($this->config['supabase_api'], '/') . '/rest/v1/' . ltrim($url, '/');
        
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSupabaseHeaders());
        
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $isHttps);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $isHttps ? 2 : false);
        
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'data' => json_decode($response, true),
            'error' => $curlError,
            'errno' => $curlErrno
        ];
    }
    
    public function testSupabaseConnection()
    {
        if (!$this->isSupabase()) {
            return ['status' => 'disabled', 'message' => '使用本地数据库'];
        }
        
        $apiUrl = $this->config['supabase_api'];
        $apiKey = $this->config['supabase_key'];
        $table = $this->config['supabase_table'];
        
        if (empty($apiUrl)) {
            return ['status' => 'error', 'message' => 'Supabase 未连接', 'details' => '未设置 API 地址'];
        }
        
        if (empty($apiKey)) {
            return ['status' => 'error', 'message' => 'Supabase 未连接', 'details' => '未设置 API Key'];
        }
        
        if (empty($table)) {
            return ['status' => 'error', 'message' => 'Supabase 未连接', 'details' => '未设置数据表名'];
        }
        
        $result = $this->callSupabase('GET', $table . '?limit=1');
        
        if ($result['code'] === 200) {
            return ['status' => 'connected', 'message' => 'Supabase 已连接'];
        } else {
            $errorMsg = '连接失败';
            if ($result['code'] === 0) {
                if (!empty($result['error'])) {
                    $errorMsg = '连接失败: ' . $result['error'];
                } else {
                    $errorMsg = '无法连接到服务器';
                }
            } elseif ($result['code'] === 401) {
                $errorMsg = 'API Key 无效';
            } elseif ($result['code'] === 403) {
                $errorMsg = '权限不足（检查 RLS 策略）';
            } elseif ($result['code'] === 404) {
                $errorMsg = '数据表不存在';
            }
            
            return ['status' => 'error', 'message' => 'Supabase 未连接', 'details' => $errorMsg];
        }
    }
    
    public function getRecord($id)
    {
        if ($this->isSupabase()) {
            $table = $this->config['supabase_table'];
            $result = $this->callSupabase('GET', $table . '?id=eq.' . $id);
            if ($result['code'] === 200 && !empty($result['data'])) {
                return $result['data'][0];
            }
            return null;
        } else {
            $table = $this->prefix . 'hamlog';
            $query = $this->db->query("SELECT * FROM `{$table}` WHERE id = " . intval($id));
            return $this->db->fetchRow($query);
        }
    }
    
    public function getRecords($page = 1, $pageSize = 20, $searchField = null, $searchKeyword = null)
    {
        $start = ($page - 1) * $pageSize;
        
        if ($this->isSupabase()) {
            $table = $this->config['supabase_table'];
            $url = $table . '?order=QSO_DATE.desc,TIME_ON.desc&limit=' . $pageSize . '&offset=' . $start;
            
            if ($searchField && $searchKeyword) {
                $validFields = ['CALL_SIGN', 'BAND', 'BAND_RX', 'MODE', 'FREQ', 'QTH', 'GRID', 'PROP_MODE', 'SAT_NAME', 'CARD_SEND', 'CARD_RCV'];
                if (in_array($searchField, $validFields)) {
                    if ($searchField === 'CARD_SEND' || $searchField === 'CARD_RCV') {
                        $url .= '&' . $searchField . '=eq.' . urlencode($searchKeyword);
                    } else {
                        $url .= '&' . $searchField . '=ilike.' . urlencode('%' . $searchKeyword . '%');
                    }
                }
            }
            
            $result = $this->callSupabase('GET', $url);
            if ($result['code'] !== 200) {
                error_log('Supabase getRecords error: ' . print_r($result, true));
            }
            return $result['code'] === 200 ? $result['data'] : [];
        } else {
            $table = $this->prefix . 'hamlog';
            $where = '';
            
            if ($searchField && $searchKeyword) {
                $validFields = ['CALL_SIGN', 'BAND', 'BAND_RX', 'MODE', 'FREQ', 'QTH', 'GRID', 'PROP_MODE', 'SAT_NAME', 'CARD_SEND', 'CARD_RCV'];
                if (in_array($searchField, $validFields)) {
                    $keyword = addslashes($searchKeyword);
                    if ($searchField === 'CARD_SEND' || $searchField === 'CARD_RCV') {
                        $where = " WHERE `{$searchField}` = '{$keyword}'";
                    } else {
                        $where = " WHERE `{$searchField}` LIKE '%{$keyword}%'";
                    }
                }
            }
            
            $sql = "SELECT * FROM `{$table}`{$where} ORDER BY QSO_DATE DESC, TIME_ON DESC LIMIT {$start}, {$pageSize}";
            return $this->db->fetchAll($sql);
        }
    }
    
    public function getTotalRecords($searchField = null, $searchKeyword = null)
    {
        if ($this->isSupabase()) {
            $table = $this->config['supabase_table'];
            $url = $table . '?select=count';
            
            if ($searchField && $searchKeyword) {
                $validFields = ['CALL_SIGN', 'BAND', 'BAND_RX', 'MODE', 'FREQ', 'QTH', 'GRID', 'PROP_MODE', 'SAT_NAME', 'CARD_SEND', 'CARD_RCV'];
                if (in_array($searchField, $validFields)) {
                    if ($searchField === 'CARD_SEND' || $searchField === 'CARD_RCV') {
                        $url .= '&' . $searchField . '=eq.' . urlencode($searchKeyword);
                    } else {
                        $url .= '&' . $searchField . '=ilike.' . urlencode('%' . $searchKeyword . '%');
                    }
                }
            }
            
            $result = $this->callSupabase('GET', $url);
            if ($result['code'] === 200 && !empty($result['data'])) {
                return intval($result['data'][0]['count']);
            }
            return 0;
        } else {
            $table = $this->prefix . 'hamlog';
            $where = '';
            
            if ($searchField && $searchKeyword) {
                $validFields = ['CALL_SIGN', 'BAND', 'BAND_RX', 'MODE', 'FREQ', 'QTH', 'GRID', 'PROP_MODE', 'SAT_NAME', 'CARD_SEND', 'CARD_RCV'];
                if (in_array($searchField, $validFields)) {
                    $keyword = addslashes($searchKeyword);
                    if ($searchField === 'CARD_SEND' || $searchField === 'CARD_RCV') {
                        $where = " WHERE `{$searchField}` = '{$keyword}'";
                    } else {
                        $where = " WHERE `{$searchField}` LIKE '%{$keyword}%'";
                    }
                }
            }
            
            $totalRow = $this->db->fetchRow("SELECT COUNT(*) AS count FROM {$table}{$where}");
            return intval($totalRow['count'] ?? 0);
        }
    }
    
    public function checkRecordExists($callSign, $qsoDate, $timeOn)
    {
        if ($this->isSupabase()) {
            $table = $this->config['supabase_table'];
            $url = $table . '?CALL_SIGN=eq.' . urlencode($callSign) . '&QSO_DATE=eq.' . urlencode($qsoDate) . '&TIME_ON=eq.' . urlencode($timeOn) . '&select=id';
            $result = $this->callSupabase('GET', $url);
            return $result['code'] === 200 && !empty($result['data']);
        } else {
            $table = $this->prefix . 'hamlog';
            $row = $this->db->fetchRow($this->db->select('id')->from($table)
                ->where('CALL_SIGN = ?', $callSign)
                ->where('QSO_DATE = ?', $qsoDate)
                ->where('TIME_ON = ?', $timeOn));
            return !empty($row);
        }
    }
    
    public function insertRecord($data)
    {
        if ($this->isSupabase()) {
            $table = $this->config['supabase_table'];
            $result = $this->callSupabase('POST', $table, $data);
            
            if ($result['code'] !== 201) {
                error_log('Supabase insert error: ' . print_r($result, true));
            }
            
            return $result['code'] === 201;
        } else {
            $table = $this->prefix . 'hamlog';
            $this->db->query($this->db->insert($table)->rows($data));
            return true;
        }
    }
    
    public function updateRecord($id, $data)
    {
        if ($this->isSupabase()) {
            $table = $this->config['supabase_table'];
            $result = $this->callSupabase('PATCH', $table . '?id=eq.' . intval($id), $data);
            
            if (!($result['code'] === 200 || $result['code'] === 204)) {
                error_log('Supabase update error: ' . print_r($result, true));
            }
            
            return $result['code'] === 200 || $result['code'] === 204;
        } else {
            $table = $this->prefix . 'hamlog';
            $this->db->query($this->db->update($table)->rows($data)->where('id = ?', intval($id)));
            return true;
        }
    }
    
    public function deleteRecord($id)
    {
        if ($this->isSupabase()) {
            $table = $this->config['supabase_table'];
            $result = $this->callSupabase('DELETE', $table . '?id=eq.' . intval($id));
            return $result['code'] === 200 || $result['code'] === 204;
        } else {
            $table = $this->prefix . 'hamlog';
            $this->db->query("DELETE FROM `{$table}` WHERE id = " . intval($id));
            return true;
        }
    }
    
    public function getProfile()
    {
        $table = $this->prefix . 'hamlog_profile';
        $row = $this->db->fetchRow($this->db->select()->from($table)->where('id = ?', 1));
        return $row ?: [];
    }
    
    public function updateProfile($data)
    {
        $table = $this->prefix . 'hamlog_profile';
        $existing = $this->db->fetchRow($this->db->select('id')->from($table)->where('id = ?', 1));
        if ($existing) {
            $this->db->query($this->db->update($table)->rows($data)->where('id = ?', 1));
        } else {
            $data['id'] = 1;
            $this->db->query($this->db->insert($table)->rows($data));
        }
        return true;
    }
}
