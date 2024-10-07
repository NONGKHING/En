<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
    <h1>เส้นทางวิกฤต</h1>
    <!-- ฟอร์มสำหรับรับค่าตัวแปร a, m, b และคำนวณค่า t, σ², ES, EF, LS, LF, Slack, Critical -->
        <form method="post">
            <table>
                <tr>
                    <th>Activity</th>
                    <th>a</th>
                    <th>m</th>
                    <th>b</th>
                    <th>t</th>
                    <th>σ²</th>
                    <th>ES</th>
                    <th>EF</th>
                    <th>LS</th>
                    <th>LF</th>
                    <th>Slack</th>
                    <th>Critical</th>
                </tr>
                <?php
                // กำหนดข้อมูลกิจกรรม พร้อม dependencies (กิจกรรมที่ต้องทำก่อน)
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

                // คลาส Activity เก็บข้อมูลกิจกรรม และคำนวณค่าที่จำเป็น
                class Activity {
                    public $name;
                    public $dependencies; // กิจกรรมที่ต้องทำก่อน
                    public $a; // ค่าความเร็วสุด (optimistic)
                    public $m; // ค่ากลาง (most likely)
                    public $b; // ค่าช้าสุด (pessimistic)
                    public $t; // เวลาคาดหวัง (expected time)
                    public $variance; // ความแปรปรวน (variance)

                    public $es; // Early Start
                    public $ef; // Early Finish
                    public $ls; // Late Start
                    public $lf; // Late Finish
                    public $slack; // Slack time

                    // กำหนดค่าเริ่มต้นสำหรับแต่ละกิจกรรม
                    public function __construct($name, $dependencies, $a, $m, $b) {
                        $this->name = $name;
                        $this->dependencies = $dependencies;
                        $this->a = $a;
                        $this->m = $m;
                        $this->b = $b;
                        // คำนวณค่า t จากสูตร (a + 4m + b) / 6
                        $this->t = ($a + 4 * $m + $b) / 6;
                        // คำนวณค่า variance จากสูตร ((b - a) / 6)²
                        $this->variance = pow(($b - $a) / 6, 2);
                    }
                }

                // เก็บข้อมูลกิจกรรมใน array
                $activities = [];
                $showResults = false;
                
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calculate'])) {
                    $showResults = true;
                    // รับค่าจากฟอร์ม และสร้างวัตถุ Activity สำหรับแต่ละกิจกรรม
                    foreach ($activities_data as $name => $data) {
                        $a = intval($_POST["a_$name"]); // ค่า a จากผู้ใช้
                        $m = intval($_POST["m_$name"]); // ค่า m จากผู้ใช้
                        $b = intval($_POST["b_$name"]); // ค่า b จากผู้ใช้
                        $activities[$name] = new Activity($name, $data['dependencies'], $a, $m, $b);
                    }

                    // คำนวณค่า ES, EF, LS, LF และ Slack
                    calculateESandEF($activities);
                    $activities = calculateLSandLF($activities);
                    $activities = calculateSlack($activities);
                    // หาเส้นทางวิกฤต
                    $criticalPath = findCriticalPath($activities);
                } else {
                    // ถ้าไม่ใช่ POST สร้างกิจกรรมที่มีค่าเริ่มต้นเป็น 0
                    foreach ($activities_data as $name => $data) {
                        $activities[$name] = new Activity($name, $data['dependencies'], 0, 0, 0);
                    }
                }

                // คำนวณ ES และ EF (Early Start และ Early Finish)
                function calculateESandEF($activities) {
                    foreach ($activities as $activity) {
                        if (empty($activity->dependencies)) {
                            $activity->es = 0; // กิจกรรมที่ไม่มี dependencies เริ่มที่ 0
                        } else {
                            $activity->es = 0;
                            foreach ($activity->dependencies as $dep) {
                                
                                foreach ($activities as $act) {
                                    if ($act->name == $dep) {
                                        // คำนวณค่า ES โดยหาค่า EF ของกิจกรรมที่พึ่งพา
                                        $activity->es = max($activity->es, $act->ef);
                                    }
                                }
                            }
                        }
                        // คำนวณ EF โดยใช้ ES + m
                        $activity->ef = $activity->es + $activity->m;
                    }
                }

                function calculateLSandLF($activities) {
                    if (empty($activities)) return [];
                    // หาค่า project duration (เวลาสิ้นสุดสูงสุด)
                    $project_duration = max(array_map(function($activity) {
                        return $activity->ef;
                    }, $activities));
                
                    // กำหนด LF สำหรับกิจกรรมที่ไม่มี dependencies ที่ตามมา
                    foreach ($activities as $name => $activity) {
                        $activity->lf = $project_duration;
                    }
                
                    // คำนวณ LS และ LF โดยการไล่จากกิจกรรมสุดท้าย
                    foreach (array_reverse($activities) as $activity) {
                        // ถ้ามี activities ที่พึ่งพากัน ให้คำนวณ LF โดยใช้ค่าต่ำสุดของ LS ของกิจกรรมที่พึ่งพา
                        foreach ($activities as $act) {
                            if (in_array($activity->name, $act->dependencies)) {
                                // อัปเดตค่า LF ของกิจกรรมตาม LS ของกิจกรรมที่พึ่งพา
                                $activity->lf = min($activity->lf, $act->ls);
                            }
                        }
                        // คำนวณ LS จาก LF - duration (m)
                        $activity->ls = $activity->lf - $activity->m;
                    }
                    return $activities; // กลับลำดับเดิม
                }
                

                // คำนวณค่า Slack
                function calculateSlack($activities) {
                    foreach ($activities as $activity) {
                        // Slack = LS - ES
                        $activity->slack = $activity->ls - $activity->es;
                    }
                    return $activities;
                }

                // หาเส้นทางวิกฤต
                function findCriticalPath($activities) {
                    $criticalPath = [];
                    foreach ($activities as $activity) {
                        if ($activity->slack == 0) {
                            // ถ้า Slack เท่ากับ 0 แสดงว่าอยู่ในเส้นทางวิกฤต
                            $criticalPath[] = $activity->name;
                        }
                    }
                    return $criticalPath;
                }

                // แสดงตารางสำหรับกรอกข้อมูล a, m, b และผลลัพธ์จากการคำนวณ
                foreach ($activities_data as $name => $data) {
                    $activityObj = $activities[$name];
                    $a = isset($_POST["a_$name"]) ? intval($_POST["a_$name"]) : 0;
                    $m = isset($_POST["m_$name"]) ? intval($_POST["m_$name"]) : 0;
                    $b = isset($_POST["b_$name"]) ? intval($_POST["b_$name"]) : 0;
                    $t = $showResults ? number_format($activityObj->t, 2) : 0;
                    $variance = $showResults ? number_format($activityObj->variance, 2) : 0;
                    $es = $showResults ? floor($activityObj->es) : 0;
                    $ef = $showResults ? floor($activityObj->ef) : 0;
                    $ls = $showResults ? floor($activityObj->ls) : 0;
                    $lf = $showResults ? floor($activityObj->lf) : 0;
                    $slack = $showResults ? number_format($activityObj->slack, 2) : 0;
                    $isCritical = $showResults ? ($activityObj->slack == 0 ? 'Yes' : 'No') : '';

                    // แสดงแถวในตารางพร้อม input ให้ผู้ใช้กรอกข้อมูลและผลลัพธ์
                    echo "<tr>";
                    echo "<td>$name</td>";
                    echo "<td><input type='number' name='a_$name' value='$a'></td>";
                    echo "<td><input type='number' name='m_$name' value='$m'></td>";
                    echo "<td><input type='number' name='b_$name' value='$b'></td>";
                    echo "<td>$t</td>";
                    echo "<td>$variance</td>";
                    echo "<td>$es</td>";
                    echo "<td>$ef</td>";
                    echo "<td>$ls</td>";
                    echo "<td>$lf</td>";
                    echo "<td>$slack</td>";
                    echo "<td class='critical'>$isCritical</td>";
                    echo "</tr>";
                }
                ?>
                </table>

                <!-- ปุ่มสำหรับรับค่า X ที่ผู้ใช้กำหนดเองและปุ่ม Calculate -->
                <label for="custom_x">X (ถ้าต้องการกำหนดเอง):</label>
                <input type="number" name="custom_x" id="custom_x" step="0.01" value="<?php echo isset($_POST['custom_x']) ? $_POST['custom_x'] : ''; ?>">
                <input type="submit" name="calculate" value="Calculate">
      </form>
      
      <?php if ($showResults) {
    // หาค่า T โดยใช้ EF ที่มากที่สุด
    $T = max(array_map(function($activity) {
        return $activity->ef;
    }, $activities));

    if (isset($_POST['custom_x']) && !empty($_POST['custom_x'])) {
        $X = floatval($_POST['custom_x']);
    } else {
        $X = array_sum(array_map(function($activity) {
            return ($activity->slack == 0) ? $activity->t : 0;
        }, $activities));
    }

    $variance_sum = array_sum(array_map(function($activity) {
        return ($activity->slack == 0) ? $activity->variance : 0;
    }, $activities));

    if ($variance_sum > 0) {
        $sigma_t = sqrt($variance_sum);
        $Z = ($T - $X) / $sigma_t;
    } else {
        $sigma_t = 0;
        $Z = $T - $X;
    }




            echo "<h2>Project Metrics</h2>";
            echo "<p><strong>Critical Path:</strong> " . implode(' -> ', $criticalPath) . "</p>";
            echo "<p><strong>X:</strong> " . number_format($X, 2) . "</p>";
            echo "<p><strong>T:</strong> " . number_format($T, 2) . "</p>";
            echo "<p><strong>σ<sub>t</sub>:</strong> " . number_format($sigma_t, 2) . "</p>";
            echo "<p><strong>Z:</strong> " . number_format($Z, 2) . "</p>";

            echo "<img src='Gianchart.png' alt=' ' style='max-width:100%; height:auto;'>" ;  
            echo "<img src='statistics.png' alt=' ' style='max-width:100%; height:auto;'>"; 
            
        } ?>
        
      
    </div>
   
</body>
</html>
