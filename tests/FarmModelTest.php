<?php
// Assuming PHPUnit is installed
require_once __DIR__ . '/../src/Modules/Farm/FarmModel.php';

use PHPUnit\Framework\TestCase;

class FarmModelTest extends TestCase {
    public function testGetAllFarms() {
        $mockPdo = $this->createMock(PDO::class);
        $mockStmt = $this->createMock(PDOStatement::class);
        
        $mockStmt->expects($this->once())
                 ->method('fetchAll')
                 ->willReturn([['id' => 1, 'name' => 'Farm 1']]);
        
        $mockPdo->expects($this->once())
                ->method('query')
                ->with("SELECT * FROM farms")
                ->willReturn($mockStmt);
        
        $farmModel = new FarmModel($mockPdo);
        $result = $farmModel->getAllFarms();
        
        $this->assertEquals([['id' => 1, 'name' => 'Farm 1']], $result);
    }
}
?>