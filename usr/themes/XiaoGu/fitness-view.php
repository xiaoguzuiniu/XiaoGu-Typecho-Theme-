<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$fitnessDates = [];
$fitnessError = '';
try {
    $fitnessDates = xiaoguFitnessCompletedDates();
} catch (\Throwable $error) {
    $fitnessError = '完成记录暂时无法读取';
    error_log('[fitness-page] ' . $error->getMessage());
}
$fitnessConfig = [
    'completedDates' => $fitnessDates,
    'endpoint' => rtrim((string) $this->options->siteUrl, '/') . '/api/fitness',
];
$fitnessExercises = [
    ['id' => 'floor-press', 'name' => '哑铃地板卧推', 'sets' => '3 组 × 8–15 次', 'rest' => '休息 90–120 秒', 'description' => '仰卧在瑜伽垫上，双手持哑铃，缓慢下降至上臂轻触地面，再向上推起。不要用腰部反弓借力。'],
    ['id' => 'shoulder-press', 'name' => '哑铃肩上推举', 'sets' => '2 组 × 8–12 次', 'rest' => '休息 90–120 秒', 'description' => '站姿或坐在稳固的无靠背坐垫上完成。收紧腹部，避免腰部过度后仰。'],
    ['id' => 'lateral-raise', 'name' => '哑铃侧平举', 'sets' => '2 组 × 12–20 次', 'rest' => '休息 60–90 秒', 'description' => '使用轻重量，手肘微弯，缓慢抬起。这个动作对肩部中束的训练很重要。'],
    ['id' => 'push-up', 'name' => '俯卧撑', 'sets' => '2 组 × 6–12 次', 'rest' => '休息 60–90 秒', 'description' => '如果标准俯卧撑做不到 6 次，可改为跪姿俯卧撑。身体保持一条直线，动作范围以肩部舒适为准。'],
];
$fitnessTuesdayExercises = [
    ['image' => 'fitness-tuesday-goblet-squat.png', 'name' => '哑铃高脚杯深蹲', 'sets' => '3 组 × 10–15 次', 'rest' => '休息 90–120 秒', 'description' => '双手在胸前抱住一只哑铃，稳定下蹲再站起。初期可以先用 4–8 kg。'],
    ['image' => 'fitness-tuesday-romanian-deadlift.png', 'name' => '哑铃罗马尼亚硬拉', 'sets' => '3 组 × 10–15 次', 'rest' => '休息 90–120 秒', 'description' => '两只哑铃贴近大腿下放，臀部向后移动，背部保持稳定。先用每只 3–5 kg 熟悉髋部折叠。'],
    ['image' => 'fitness-tuesday-glute-bridge.png', 'name' => '臀桥', 'sets' => '2 组 × 12–20 次', 'rest' => '休息 60–90 秒', 'description' => '仰卧屈膝，抬起臀部至身体基本成直线。熟练后可将轻哑铃放在髋部增加阻力。'],
    ['image' => 'fitness-tuesday-calf-raise.png', 'name' => '提踵', 'sets' => '2 组 × 15–25 次', 'rest' => '休息 45–60 秒', 'description' => '缓慢踮起脚跟，再控制下降。先徒手练习，稳定后再手持哑铃。'],
    ['image' => 'fitness-tuesday-dead-bug.png', 'name' => 'Dead Bug 死虫式', 'sets' => '2 组 × 每侧 8–10 次', 'rest' => '休息 45–60 秒', 'description' => '仰卧交替伸展对侧手脚，保持腰部稳定，不要为了伸得更远而塌腰。'],
];
?>

<div class="fitness-page" id="fitness-page">
    <section class="fitness-character" aria-label="个人像素形象">
        <svg viewBox="0 0 80 112" aria-hidden="true" focusable="false" shape-rendering="crispEdges">
            <path fill="#33385d" d="M24 4h32v8H24zM16 12h48v24H16zM24 36h32v8H24z"/>
            <path fill="#f7c69d" d="M24 20h32v32H24zM16 28h8v16h-8zM56 28h8v16h-8z"/>
            <path fill="#33385d" d="M28 28h8v8h-8zM44 28h8v8h-8z"/>
            <path fill="#de9279" d="M32 44h16v4H32z"/>
            <path fill="#5759e8" d="M20 52h40v36H20zM12 60h8v24h-8zM60 60h8v24h-8z"/>
            <path fill="#e0e5ff" d="M36 52h8v12h-8z"/>
            <path fill="#343b71" d="M24 88h16v16H24zM40 88h16v16H40z"/>
            <path fill="#242b50" d="M16 104h24v8H16zM40 104h24v8H40z"/>
        </svg>
    </section>

    <section class="fitness-calendar fitness-panel" aria-label="健身日历">
        <div class="fitness-panel-head">
            <h2 id="fitness-month-title"></h2>
            <div class="fitness-month-actions">
                <button type="button" id="fitness-prev-month" aria-label="上个月">‹</button>
                <button type="button" id="fitness-today" aria-label="回到今天">今天</button>
                <button type="button" id="fitness-next-month" aria-label="下个月">›</button>
            </div>
        </div>
        <div class="fitness-weekdays" aria-hidden="true"><span>一</span><span>二</span><span>三</span><span>四</span><span>五</span><span>六</span><span>日</span></div>
        <div class="fitness-calendar-days" id="fitness-calendar-days" aria-label="选择训练日期"></div>
        <?php if ($fitnessError !== ''): ?><p class="fitness-message" role="status"><?php echo htmlspecialchars($fitnessError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </section>

    <section class="fitness-workout fitness-panel" aria-label="当天训练安排">
        <div class="fitness-workout-content" id="fitness-workout-content" aria-label="训练动作列表" aria-live="polite" tabindex="0"></div>
    </section>
</div>

<dialog class="fitness-checkin-dialog" id="fitness-checkin-dialog" aria-labelledby="fitness-checkin-title">
    <div class="fitness-checkin-dialog-head">
        <div><span class="fitness-panel-label">打卡详情</span><h2 id="fitness-checkin-title"></h2></div>
        <button type="button" id="fitness-checkin-close" aria-label="关闭打卡详情">×</button>
    </div>
    <div class="fitness-checkin-dialog-body" id="fitness-checkin-content" aria-live="polite"></div>
</dialog>

<template id="fitness-monday-template">
    <ol class="fitness-exercise-list">
        <?php foreach ($fitnessExercises as $index => $exercise): ?>
            <li class="fitness-exercise">
                <div class="fitness-exercise-art" aria-hidden="true">
                    <svg viewBox="0 0 96 96" focusable="false"><use href="<?php $this->options->themeUrl('assets/fitness-exercises.svg'); ?>#<?php echo $exercise['id']; ?>"></use></svg>
                </div>
                <div class="fitness-exercise-copy">
                    <h3><span><?php echo sprintf('%02d', $index + 1); ?></span><?php echo htmlspecialchars($exercise['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="fitness-exercise-stats">
                        <strong><?php echo htmlspecialchars($exercise['sets'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars($exercise['rest'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <p class="fitness-exercise-description" id="fitness-exercise-description-<?php echo $index + 1; ?>"><?php echo htmlspecialchars($exercise['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <button class="fitness-exercise-toggle" type="button" aria-expanded="false" aria-controls="fitness-exercise-description-<?php echo $index + 1; ?>">展开要点</button>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</template>
<template id="fitness-tuesday-template">
    <ol class="fitness-exercise-list">
        <?php foreach ($fitnessTuesdayExercises as $index => $exercise): ?>
            <li class="fitness-exercise">
                <div class="fitness-exercise-art">
                    <img src="<?php $this->options->themeUrl('assets/' . $exercise['image']); ?>"
                         alt="<?php echo htmlspecialchars($exercise['name'] . '动作示范', ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
                </div>
                <div class="fitness-exercise-copy">
                    <h3><span><?php echo sprintf('%02d', $index + 1); ?></span><?php echo htmlspecialchars($exercise['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="fitness-exercise-stats">
                        <strong><?php echo htmlspecialchars($exercise['sets'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars($exercise['rest'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <p class="fitness-exercise-description" id="fitness-tuesday-exercise-description-<?php echo $index + 1; ?>"><?php echo htmlspecialchars($exercise['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <button class="fitness-exercise-toggle" type="button" aria-expanded="false" aria-controls="fitness-tuesday-exercise-description-<?php echo $index + 1; ?>">展开要点</button>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</template>
<template id="fitness-empty-template">
    <div class="fitness-workout-empty"><span aria-hidden="true">◇</span><strong>今天的训练计划还没添加</strong><p>目前已录入周一、周二训练示例，其他日期的安排可以继续补充。</p></div>
</template>
<script type="application/json" id="fitness-config"><?php echo json_encode($fitnessConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
