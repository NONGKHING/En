<!DOCTYPE html>
<html lang="en"> <!-- กำหนดประเภทของเอกสารเป็น HTML และระบุว่าเอกสารนี้เป็นภาษาอังกฤษ -->
<head>
    <meta charset="UTF-8"> <!-- กำหนดการเข้ารหัสตัวอักษรเป็น UTF-8 เพื่อรองรับอักขระพิเศษ -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- ตั้งค่าการแสดงผลสำหรับอุปกรณ์มือถือ -->
    <title>Document</title> <!-- ตั้งชื่อเอกสารที่จะแสดงในแท็บของเบราว์เซอร์ -->
    <link rel="stylesheet" href="styles.css"> <!-- เชื่อมโยงไปยังไฟล์ CSS เพื่อการจัดรูปแบบ -->
</head>
<body>
    <div class="container"> <!-- สร้าง div สำหรับจัดกลุ่มเนื้อหา -->
        <h1>เส้นทางวิกฤต</h1> <!-- หัวข้อหลักของเอกสาร -->
        <!-- ฟอร์มสำหรับรับค่าตัวแปร a, m, b และคำนวณค่า t, σ², ES, EF, LS, LF, Slack, Critical -->
        <form method="post"> <!-- สร้างฟอร์มที่ใช้วิธี POST เพื่อส่งข้อมูล -->
            <table> <!-- เริ่มสร้างตาราง -->
                <tr> <!-- เริ่มแถวในตาราง -->
                    <th>Activity</th> <!-- หัวข้อสำหรับชื่อกิจกรรม -->
                    <th>a</th> <!-- หัวข้อสำหรับค่า a (ค่าความเร็วสุด) -->
                    <th>m</th> <!-- หัวข้อสำหรับค่า m (ค่ากลาง) -->
                    <th>b</th> <!-- หัวข้อสำหรับค่า b (ค่าช้าสุด) -->
                    <th>t</th> <!-- หัวข้อสำหรับเวลา t (เวลาคาดหวัง) -->
                    <th>σ²</th> <!-- หัวข้อสำหรับความแปรปรวน -->
                    <th>ES</th> <!-- หัวข้อสำหรับ Early Start -->
                    <th>EF</th> <!-- หัวข้อสำหรับ Early Finish -->
                    <th>LS</th> <!-- หัวข้อสำหรับ Late Start -->
                    <th>LF</th> <!-- หัวข้อสำหรับ Late Finish -->
                    <th>Slack</th> <!-- หัวข้อสำหรับ Slack time -->
                    <th>Critical</th> <!-- หัวข้อสำหรับสถานะ Critical -->
                </tr>
                <?php
                // กำหนดข้อมูลกิจกรรม โดยมี dependencies (กิจกรรมที่ต้องทำก่อน)
                $activities_data = [
                    'A' => ['dependencies' => ['']],
                    'B' => ['dependencies' => ['A']],
                    'C' => ['dependencies' => ['A']],
                    'D' => ['dependencies' => ['C']],
                    'E' => ['dependencies' => ['B']],
                    'F' => ['dependencies' => ['E','D']],
                    'G' => ['dependencies' => ['F']],
                    'H' => ['dependencies' => ['B']],
                    'I' => ['dependencies' => ['G']],
                    'J' => ['dependencies' => ['H']],
                    'K' => ['dependencies' => ['J','I']],
                    'L' => ['dependencies' => ['K']],
                    'M' => ['dependencies' => ['H']],
                    'N' => ['dependencies' => ['M']],
                    'O' => ['dependencies' => ['L']]
                ];

                // คลาส Activity สำหรับจัดเก็บข้อมูลกิจกรรม และคำนวณค่าที่จำเป็น
                class Activity {
                    public $name; // ตัวแปรสำหรับชื่อกิจกรรม
                    public $dependencies; // ตัวแปรสำหรับกิจกรรมที่ต้องทำก่อน
                    public $a; // ตัวแปรสำหรับค่าความเร็วสุด
                    public $m; // ตัวแปรสำหรับค่ากลาง
                    public $b; // ตัวแปรสำหรับค่าช้าสุด
                    public $t; // ตัวแปรสำหรับเวลา (expected time)
                    public $variance; // ตัวแปรสำหรับความแปรปรวน

                    public $es; // ตัวแปรสำหรับ Early Start
                    public $ef; // ตัวแปรสำหรับ Early Finish
                    public $ls; // ตัวแปรสำหรับ Late Start
                    public $lf; // ตัวแปรสำหรับ Late Finish
                    public $slack; // ตัวแปรสำหรับ Slack time

                    // ฟังก์ชัน constructor สำหรับกำหนดค่าเริ่มต้นของแต่ละกิจกรรม
                    public function __construct($name, $dependencies, $a, $m, $b) {
                        $this->name = $name; // กำหนดชื่อกิจกรรม
                        $this->dependencies = $dependencies; // กำหนด dependencies ของกิจกรรม
                        $this->a = $a; // กำหนดค่า a
                        $this->m = $m; // กำหนดค่า m
                        $this->b = $b; // กำหนดค่า b
                        // คำนวณค่า t จากสูตร (a + 4m + b) / 6
                        $this->t = ($a + 4 * $m + $b) / 6; 
                        // คำนวณค่า variance จากสูตร ((b - a) / 6)²
                        $this->variance = pow(($b - $a) / 6, 2); 
                    }
                }

                // เก็บข้อมูลกิจกรรมในอาร์เรย์
                $activities = []; // อาร์เรย์สำหรับเก็บกิจกรรม
                $showResults = false; // ตัวแปรสำหรับแสดงผลลัพธ์

                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calculate'])) {
                    $showResults = true; // ถ้ามีการส่งฟอร์มให้แสดงผลลัพธ์
                    // รับค่าจากฟอร์ม และสร้างวัตถุ Activity สำหรับแต่ละกิจกรรม
                    foreach ($activities_data as $name => $data) {
                        $a = intval($_POST["a_$name"]); // รับค่า a จากผู้ใช้และแปลงเป็นจำนวนเต็ม
                        $m = intval($_POST["m_$name"]); // รับค่า m จากผู้ใช้และแปลงเป็นจำนวนเต็ม
                        $b = intval($_POST["b_$name"]); // รับค่า b จากผู้ใช้และแปลงเป็นจำนวนเต็ม
                        $activities[$name] = new Activity($name, $data['dependencies'], $a, $m, $b); // สร้างวัตถุ Activity
                    }

                    // คำนวณค่า ES, EF, LS, LF และ Slack
                    calculateESandEF($activities); // คำนวณ ES และ EF
                    $activities = calculateLSandLF($activities); // คำนวณ LS และ LF
                    $activities = calculateSlack($activities); // คำนวณ Slack
                    // หาเส้นทางวิกฤต
                    $criticalPath = findCriticalPath($activities); // ค้นหาเส้นทางวิกฤต
                } else {
                    // ถ้าไม่ใช่ POST ให้สร้างกิจกรรมที่มีค่าเริ่มต้นเป็น 0
                    foreach ($activities_data as $name => $data) {
                        $activities[$name] = new Activity($name, $data['dependencies'], 0, 0, 0); // สร้างวัตถุ Activity ด้วยค่าเริ่มต้น 0
                    }
                }

                // ฟังก์ชันคำนวณ ES และ EF (Early Start และ Early Finish)
                function calculateESandEF($activities) {
                    foreach ($activities as $activity) { // สำหรับแต่ละกิจกรรม
                        if (empty($activity->dependencies)) { // ถ้ากิจกรรมไม่มี dependencies
                            $activity->es = 0; // กำหนด Early Start เป็น 0
                        } else {
                            $activity->es = 0; // ตั้งค่า Early Start เป็น 0
                            foreach ($activity->dependencies as $dep) { // สำหรับแต่ละ dependency
                                
                                foreach ($activities as $act) {
                                    if ($act->name == $dep) { // ถ้าชื่อกิจกรรมตรงกับ dependency
                                        // คำนวณค่า Early Start โดยหาค่า Early Finish ของกิจกรรมที่พึ่งพา
                                        $activity->es = max($activity->es, $act->ef); // กำหนด Early Start เป็นค่ามากที่สุดของ Early Finish
                                    }
                                }
                            }
                        }
                        // คำนวณ Early Finish โดยใช้ Early Start + m
                        $activity->ef = $activity->es + $activity->m; // คำนวณ Early Finish
                    }
                }

                // ฟังก์ชันคำนวณ LS และ LF
                function calculateLSandLF($activities) {
                    if (empty($activities)) return []; // ถ้าไม่มีกิจกรรมให้คืนค่ากลับเป็นอาร์เรย์ว่าง
                    // หาค่า project duration (เวลาสิ้นสุดสูงสุด)
                    $project_duration = max(array_map(function($activity) {
                        return $activity->ef; // หาค่า Early Finish สูงสุด
                    }, $activities));
                
                    // กำหนด LF สำหรับกิจกรรมที่ไม่มี dependencies ที่ตามมา
                    foreach ($activities as $name => $activity) {
                        $activity->lf = $project_duration; // กำหนด LF เป็นเวลาสิ้นสุดสูงสุดของโครงการ
                    }
                    foreach ($activities as $activity) { // สำหรับแต่ละกิจกรรม
                        if (!empty($activity->dependencies)) { // ถ้ามีกิจกรรมที่ต้องพึ่งพา
                            foreach ($activity->dependencies as $dep) { // สำหรับแต่ละ dependency
                                foreach ($activities as $act) {
                                    if ($act->name == $dep) { // ถ้าชื่อกิจกรรมตรงกับ dependency
                                        // กำหนด LF เป็นค่าต่ำสุดของ LS
                                        $activity->lf = min($activity->lf, $act->ls); // กำหนด LF เป็นค่าต่ำสุดของ LS
                                    }
                                }
                            }
                        }
                        // คำนวณ LS จาก LF - duration (m)
                        $activity->ls = $activity->lf - $activity->m; // คำนวณ LS
                    }
                    return $activities; // ส่งกลับกิจกรรม
                }

                // ฟังก์ชันคำนวณ Slack
                function calculateSlack($activities) {
                    foreach ($activities as $activity) {
                        // Slack = LS - ES
                        $activity->slack = $activity->ls - $activity->es; // คำนวณค่า Slack
                    }
                    return $activities; // ส่งกลับกิจกรรม
                }

                // ฟังก์ชันหาเส้นทางวิกฤต
                function findCriticalPath($activities) {
                    $criticalPath = []; // อาร์เรย์สำหรับเก็บเส้นทางวิกฤต
                    foreach ($activities as $activity) {
                        if ($activity->slack == 0) { // ถ้า Slack เท่ากับ 0
                            // ถ้า Slack เท่ากับ 0 แสดงว่าอยู่ในเส้นทางวิกฤต
                            $criticalPath[] = $activity->name; // เพิ่มกิจกรรมในเส้นทางวิกฤต
                        }
                    }
                    return $criticalPath; // ส่งกลับเส้นทางวิกฤต
                }

                // แสดงตารางสำหรับกรอกข้อมูล a, m, b และผลลัพธ์จากการคำนวณ
                foreach ($activities_data as $name => $data) {
                    $activityObj = $activities[$name]; // รับวัตถุ Activity
                    $a = isset($_POST["a_$name"]) ? intval($_POST["a_$name"]) : 0; // ค่า a จากผู้ใช้
                    $m = isset($_POST["m_$name"]) ? intval($_POST["m_$name"]) : 0; // ค่า m จากผู้ใช้
                    $b = isset($_POST["b_$name"]) ? intval($_POST["b_$name"]) : 0; // ค่า b จากผู้ใช้
                    $t = $showResults ? number_format($activityObj->t, 2) : 0; // ค่าที่คาดหวัง (t)
                    $variance = $showResults ? number_format($activityObj->variance, 2) : 0; // ค่า variance
                    $es = $showResults ? floor($activityObj->es) : 0; // Early Start
                    $ef = $showResults ? floor($activityObj->ef) : 0; // Early Finish
                    $ls = $showResults ? floor($activityObj->ls) : 0; // Late Start
                    $lf = $showResults ? floor($activityObj->lf) : 0; // Late Finish
                    $slack = $showResults ? number_format($activityObj->slack, 2) : 0; // Slack
                    $isCritical = $showResults ? ($activityObj->slack == 0 ? 'Yes' : 'No') : ''; // ตรวจสอบว่ากิจกรรมอยู่ในเส้นทางวิกฤตหรือไม่

                    // แสดงแถวในตารางพร้อม input ให้ผู้ใช้กรอกข้อมูลและผลลัพธ์
                    echo "<tr>"; // เริ่มแถวในตาราง
                    echo "<td>$name</td>"; // แสดงชื่อกิจกรรม
                    echo "<td><input type='number' name='a_$name' value='$a'></td>"; // ช่องกรอกค่า a
                    echo "<td><input type='number' name='m_$name' value='$m'></td>"; // ช่องกรอกค่า m
                    echo "<td><input type='number' name='b_$name' value='$b'></td>"; // ช่องกรอกค่า b
                    echo "<td>$t</td>"; // แสดงค่า t
                    echo "<td>$variance</td>"; // แสดงค่า variance
                    echo "<td>$es</td>"; // แสดงค่า ES
                    echo "<td>$ef</td>"; // แสดงค่า EF
                    echo "<td>$ls</td>"; // แสดงค่า LS
                    echo "<td>$lf</td>"; // แสดงค่า LF
                    echo "<td>$slack</td>"; // แสดงค่า Slack
                    echo "<td class='critical'>$isCritical</td>"; // แสดงสถานะ Critical
                    echo "</tr>"; // สิ้นสุดแถว
                }
                ?>
                </table>

                <!-- ปุ่มสำหรับรับค่า X ที่ผู้ใช้กำหนดเองและปุ่ม Calculate -->
                <label for="custom_x">X (ถ้าต้องการกำหนดเอง):</label> <!-- ป้ายสำหรับช่องกรอกค่า X -->
                <input type="number" name="custom_x" id="custom_x" step="0.01" value="<?php echo isset($_POST['custom_x']) ? $_POST['custom_x'] : ''; ?>"> <!-- ช่องกรอกค่า X -->
                <input type="submit" name="calculate" value="Calculate"> <!-- ปุ่มสำหรับคำนวณ -->
            </form>
            
            <?php if ($showResults) { // ถ้ามีการส่งฟอร์มให้แสดงผลลัพธ์
                // หาค่า T โดยใช้ EF ที่มากที่สุด
                $T = max(array_map(function($activity) {
                    return $activity->ef; // หาค่า EF สูงสุด
                }, $activities));

                if (isset($_POST['custom_x']) && !empty($_POST['custom_x'])) {
                    $X = floatval($_POST['custom_x']); // รับค่าจากช่องกรอก X
                } else {
                    $X = array_sum(array_map(function($activity) {
                        return ($activity->slack == 0) ? $activity->t : 0; // คำนวณค่า X จากกิจกรรมที่อยู่ในเส้นทางวิกฤต
                    }, $activities));
                }

                $variance_sum = array_sum(array_map(function($activity) {
                    return ($activity->slack == 0) ? $activity->variance : 0; // คำนวณความแปรปรวนรวมจากกิจกรรมที่อยู่ในเส้นทางวิกฤต
                }, $activities));

                if ($variance_sum > 0) {
                    $sigma_t = sqrt($variance_sum); // คำนวณค่า σ<sub>t</sub>
                    $Z = ($T - $X) / $sigma_t; // คำนวณค่า Z
                } else {
                    $sigma_t = 0; // ถ้าความแปรปรวนรวมเท่ากับ 0 กำหนด σ<sub>t</sub> เป็น 0
                    $Z = $T - $X; // คำนวณ Z โดยไม่ใช้ σ<sub>t</sub>
                }

                // แสดงผลลัพธ์
                echo "<h2>Project Metrics</h2>"; // หัวข้อสำหรับเมตริกของโครงการ
                echo "<p><strong>Critical Path:</strong> " . implode(' -> ', $criticalPath) . "</p>"; // แสดงเส้นทางวิกฤต
                echo "<p><strong>X:</strong> " . number_format($X, 2) . "</p>"; // แสดงค่า X
                echo "<p><strong>T:</strong> " . number_format($T, 2) . "</p>"; // แสดงค่า T
                echo "<p><strong>σ<sub>t</sub>:</strong> " . number_format($sigma_t, 2) . "</p>"; // แสดงค่า σ<sub>t</sub>
                echo "<p><strong>Z:</strong> " . number_format($Z, 2) . "</p>"; // แสดงค่า Z

                echo "<img src='1.1.png' alt=' ' style='max-width:100%; height:auto;'>" ; // แสดงภาพที่ 1
                echo "<img src='1.2.png' alt=' ' style='max-width:100%; height:auto;'>"; // แสดงภาพที่ 2
            } ?>
        </div>
    </body>
</html>
