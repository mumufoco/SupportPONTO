<?php
declare(strict_types=1);
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=supportponto','postgres','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$metrics = [];
$safe = static function(PDO $pdo, string $sql, int $default=0):int{
    try{return (int)$pdo->query($sql)->fetchColumn();}catch(Throwable){return $default;}
};

// 1. Erros/5min no log CI4
$logFile = __DIR__.'/../writable/logs/log-'.date('Y-m-d').'.log';
$errorCount = 0;
if(file_exists($logFile)){
    $cutoff = date('Y-m-d H:i:s',strtotime('-5 minutes'));
    foreach(array_reverse(file($logFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[]) as $line){
        if(!preg_match('/^(?:ERROR|CRITICAL)\s*-\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/',$line,$m)) continue;
        if($m[1]<$cutoff) break;
        $errorCount++;
    }
}
$metrics[]=  ['metric'=>'errors_per_5min','value'=>$errorCount,'labels'=>'{}'];

// 2. Jobs
$metrics[]=['metric'=>'jobs_pending','value'=>$safe($pdo,"SELECT COUNT(*) FROM jobs WHERE status='pending'"),'labels'=>'{}'];
$metrics[]=['metric'=>'jobs_failed', 'value'=>$safe($pdo,"SELECT COUNT(*) FROM jobs WHERE status='failed'"), 'labels'=>'{}'];

// 3. Justificativas pendentes
$metrics[]=['metric'=>'justifications_pending','value'=>$safe($pdo,"SELECT COUNT(*) FROM justifications WHERE status='pendente' AND deleted_at IS NULL"),'labels'=>'{}'];

// 4. Pontos pendentes de revisao
$metrics[]=['metric'=>'pending_punches','value'=>$safe($pdo,"SELECT COUNT(*) FROM pending_punches WHERE status='pending'"),'labels'=>'{}'];

// 5. Colaboradores sem biometria
$metrics[]=['metric'=>'employees_no_biometric','value'=>$safe($pdo,"SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL AND id NOT IN(SELECT DISTINCT employee_id FROM biometric_templates WHERE is_active=true)"),'labels'=>'{}'];

// 6. Registros de ponto hoje
$metrics[]=['metric'=>'punches_today','value'=>$safe($pdo,"SELECT COUNT(*) FROM time_punches WHERE DATE(punch_time)=CURRENT_DATE"),'labels'=>'{}'];

// 7. Colaboradores com ponto hoje
$metrics[]=['metric'=>'employees_active_today','value'=>$safe($pdo,"SELECT COUNT(DISTINCT employee_id) FROM time_punches WHERE DATE(punch_time)=CURRENT_DATE"),'labels'=>'{}'];

// Persiste
$stmt=$pdo->prepare("INSERT INTO metrics_timeseries(recorded_at,metric,value,labels) VALUES(NOW(),:metric,:value,:labels::jsonb)");
foreach($metrics as $m) $stmt->execute([':metric'=>$m['metric'],':value'=>$m['value'],':labels'=>$m['labels']]);

// Retencao 30 dias
$pdo->exec("DELETE FROM metrics_timeseries WHERE recorded_at < NOW() - INTERVAL '30 days'");

echo '['.date('H:i:s').'] '.count($metrics)." metricas coletadas.
";
foreach($metrics as $m) echo "  {$m['metric']} = {$m['value']}
";
