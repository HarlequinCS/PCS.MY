<?php
/**
 * Test INSERT directly
 * This will show the exact error
 */

header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'u284911514_pcsmy';
$username = 'u284911514_pcsadmin';
$password = 'PCSadmin25';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo json_encode(['step' => 'connected', 'status' => 'ok']);
    
    // Test INSERT
    $testComment = "Test comment " . time();
    $testIp = "127.0.0.1";
    
    echo json_encode(['step' => 'preparing_insert', 'comment' => $testComment]);
    
    $stmt = $pdo->prepare("INSERT INTO comment (comment, status, ip_address) VALUES (?, ?, ?)");
    
    echo json_encode(['step' => 'executing_insert']);
    
    $result = $stmt->execute([$testComment, 'pending', $testIp]);
    
    echo json_encode(['step' => 'insert_executed', 'result' => $result]);
    
    $commentId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'comment_id' => $commentId,
        'message' => 'INSERT successful!'
    ]);
    
    // Clean up test
    if ($commentId) {
        $pdo->prepare("DELETE FROM comment WHERE comment_id = ?")->execute([$commentId]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'PDO Error',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'errorInfo' => $e->errorInfo,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'General Error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

