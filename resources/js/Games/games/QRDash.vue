<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    config: Object,
});

// Slightly larger playfield for phones + better feel than the old 320px canvas
const CANVAS_WIDTH = 360;
const CANVAS_HEIGHT = 520;
const PLAYER_SIZE = 42;
const GROUND_HEIGHT = 20;
const GROUND_TOP = CANVAS_HEIGHT - GROUND_HEIGHT;
const GRAVITY = 0.4;
const JUMP_FORCE = -12;
const GAME_SPEED = 3.5;

const canvas = ref(null);
const ctx = ref(null);
const animationFrame = ref(null);
const qrCodeImage = ref(null);

const player = ref({ 
    x: 80, 
    y: GROUND_TOP - PLAYER_SIZE / 2,
    velocity: 0, 
    rotation: 0
});
const obstacles = ref([]);
const pits = ref([]);
const currentLevel = ref(1);
const distanceTraveled = ref(0);
const startTime = ref(null);
const backgroundOffset = ref(0);
const gameSpeed = ref(GAME_SPEED);
const lastSurvivalSecondScored = ref(0);
const nextSpeedIncreaseAt = ref(2000);
const jumpsRemaining = ref(2); // Double jump (2 jumps total)
const isOnGround = ref(true);
const levelCompleted = ref(false);
const showLevelComplete = ref(false);
const lastObstacleX = ref(CANVAS_WIDTH);

// Generate QR code image
const generateQRCode = async (forceRegenerate = false) => {
    if (qrCodeImage.value && !forceRegenerate) return; // Already generated
    
    const qrData = `game://qr-dash/level-${currentLevel.value}`;
    const encodedUrl = encodeURIComponent(qrData);
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodedUrl}&bgcolor=ffffff&color=000000&margin=0`;
    
    try {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            qrCodeImage.value = img;
        };
        img.src = qrUrl;
    } catch (error) {
        console.error('Failed to load QR code:', error);
    }
};

// Initialize game
const initGame = () => {
    player.value = { 
        x: 80, 
        y: GROUND_TOP - PLAYER_SIZE / 2,
        velocity: 0, 
        rotation: 0
    };
    obstacles.value = [];
    pits.value = [];
    lastObstacleX.value = CANVAS_WIDTH;
    distanceTraveled.value = 0;
    backgroundOffset.value = 0;
    gameSpeed.value = GAME_SPEED;
    startTime.value = Date.now(); // autorun immediately (Geometry Dash style)
    lastSurvivalSecondScored.value = 0;
    nextSpeedIncreaseAt.value = 2000;
    jumpsRemaining.value = 2; // Reset double jump
    isOnGround.value = true;
    levelCompleted.value = false;
    showLevelComplete.value = false;
    
    // Generate level obstacles
    generateLevel(currentLevel.value);
};

// Generate obstacles for each level with random spacing
const generateLevel = (level) => {
    obstacles.value = [];
    pits.value = [];
    lastObstacleX.value = CANVAS_WIDTH;
    
    const count = level === 1 ? 35 : (level === 2 ? 55 : 50);
    for (let i = 0; i < count; i++) {
        createRandomObstacle();
    }
};

const createRandomObstacle = () => {
    // Spacing gets tighter as levels go up
    const baseSpacing = currentLevel.value === 1 ? 220 : (currentLevel.value === 2 ? 190 : 170);
    const spacing = baseSpacing + Math.random() * 150;
    
    lastObstacleX.value += spacing;
    const x = lastObstacleX.value;
    
    const rand = Math.random();
    
    // Pit logic: No pits in Level 1, 8% in Level 2, 15% in Level 3
    const pitChance = currentLevel.value === 1 ? 0 : (currentLevel.value === 2 ? 0.08 : 0.15);
    if (rand < pitChance) {
        pits.value.push({
            x: x,
            width: 85 + Math.random() * 45,
            passed: false
        });
        return;
    }
    
    // Obstacle distribution changes by level
    if (rand < 0.5) {
        // Red Triangle Spikes
        // Level 1: mostly 1. Level 2: 1-2. Level 3: 1-3.
        let maxSpikes = currentLevel.value === 1 ? 1 : (currentLevel.value === 2 ? 2 : 3);
        const spikeCount = Math.floor(Math.random() * maxSpikes) + 1;
        const spikeWidth = 30;
        const spikeHeight = 20;
        
        for (let i = 0; i < spikeCount; i++) {
            obstacles.value.push({
                x: x + (i * spikeWidth),
                y: GROUND_TOP - spikeHeight,
                width: spikeWidth,
                height: spikeHeight,
                type: 'spike',
                passed: false,
            });
        }
    } else if (rand < 0.8) {
        // Blue Blocks
        // Level 1: No stacks. Level 2: 30% stacks. Level 3: 50% stacks.
        const stackChance = currentLevel.value === 1 ? 0 : (currentLevel.value === 2 ? 0.3 : 0.5);
        const isStacked = Math.random() < stackChance;
        const blockWidth = 40;
        const blockHeight = 40;
        
        obstacles.value.push({
            x: x,
            y: GROUND_TOP - blockHeight,
            width: blockWidth,
            height: blockHeight,
                    type: 'block',
                    passed: false,
        });
        
        if (isStacked) {
            obstacles.value.push({
                x: x,
                y: GROUND_TOP - (blockHeight * 2),
                width: blockWidth,
                height: blockHeight,
                    type: 'block',
                    passed: false,
            });
            }
    } else {
        // Platforms (always safe to land on)
        const platformWidth = 70 + Math.random() * 50;
        const platformHeight = 20;
        const platformY = GROUND_TOP - 85 - Math.random() * 90;
        
        obstacles.value.push({
            x: x,
            y: platformY,
            width: platformWidth,
            height: platformHeight,
            type: 'block',
            passed: false,
        });
    }
};

// Generate procedural obstacles for level 3 with random spacing
const generateProceduralObstacles = () => {
    generateLevel(3);
};

// Add more obstacles for level 3 (procedural) with random spacing
const addMoreObstacles = () => {
    if (currentLevel.value !== 3) return;
    
    const lastX = lastObstacleX.value;
    if (lastX < distanceTraveled.value + CANVAS_WIDTH + 500) {
        createRandomObstacle();
    }
};

// Jump function with double jump support
const jump = () => {
    // Handle level completion click
    if (levelCompleted.value && showLevelComplete.value) {
        proceedToNextLevel();
        return;
    }
    
    if (props.gameState !== 'playing' || levelCompleted.value) return;
    
    // Check if player can jump (has jumps remaining)
    if (jumpsRemaining.value > 0) {
        player.value.velocity = JUMP_FORCE;
        jumpsRemaining.value--;
        isOnGround.value = false;
    }
};

// Proceed to next level after completion
const proceedToNextLevel = () => {
    if (currentLevel.value === 1) {
        currentLevel.value = 2;
        props.addScore(500);
        generateQRCode(true); // Regenerate QR code for new level
    } else if (currentLevel.value === 2) {
        currentLevel.value = 3;
        props.addScore(1000);
        generateQRCode(true); // Regenerate QR code for new level
    }
    levelCompleted.value = false;
    showLevelComplete.value = false;
    initGame();
};

// Draw function
const draw = () => {
    if (!ctx.value) return;
    
    const c = ctx.value;
    
    // Background gradient (dark theme like Geometry Dash)
    const gradient = c.createLinearGradient(0, 0, 0, CANVAS_HEIGHT);
    gradient.addColorStop(0, '#1a1a2e');
    gradient.addColorStop(1, '#16213e');
    c.fillStyle = gradient;
    c.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
    
    // Grid pattern background
    c.strokeStyle = 'rgba(255, 255, 255, 0.05)';
    c.lineWidth = 1;
    const gridOffset = backgroundOffset.value % 40;
    for (let x = -gridOffset; x < CANVAS_WIDTH; x += 40) {
        c.beginPath();
        c.moveTo(x, 0);
        c.lineTo(x, CANVAS_HEIGHT);
        c.stroke();
    }
    for (let y = -gridOffset; y < CANVAS_HEIGHT; y += 40) {
        c.beginPath();
        c.moveTo(0, y);
        c.lineTo(CANVAS_WIDTH, y);
        c.stroke();
    }
    
    // Draw obstacles
    obstacles.value.forEach(obstacle => {
        if (obstacle.x + obstacle.width < 0 || obstacle.x > CANVAS_WIDTH) return;
        
        c.fillStyle = obstacle.type === 'spike' ? '#ef4444' : '#3b82f6';
        c.strokeStyle = obstacle.type === 'spike' ? '#dc2626' : '#2563eb';
        c.lineWidth = 2;
        
        if (obstacle.type === 'spike') {
            // Draw triangle spike
            c.beginPath();
            c.moveTo(obstacle.x, obstacle.y + obstacle.height);
            c.lineTo(obstacle.x + obstacle.width / 2, obstacle.y);
            c.lineTo(obstacle.x + obstacle.width, obstacle.y + obstacle.height);
            c.closePath();
            c.fill();
            c.stroke();
        } else {
            // Draw block
            c.fillRect(obstacle.x, obstacle.y, obstacle.width, obstacle.height);
            c.strokeRect(obstacle.x, obstacle.y, obstacle.width, obstacle.height);
        }
    });
    
    // Draw ground
    c.fillStyle = '#374151';
    c.strokeStyle = '#4b5563';
    c.lineWidth = 2;

    // Draw floor segments (only where there are no pits)
    let currentFloorX = 0;
    const sortedPits = [...pits.value].sort((a, b) => a.x - b.x);
    
    sortedPits.forEach(pit => {
        if (pit.x > currentFloorX) {
            c.fillRect(currentFloorX, GROUND_TOP, pit.x - currentFloorX, GROUND_HEIGHT);
            c.strokeRect(currentFloorX, GROUND_TOP, pit.x - currentFloorX, GROUND_HEIGHT);
        }
        currentFloorX = pit.x + pit.width;
    });
    
    if (currentFloorX < CANVAS_WIDTH) {
        c.fillRect(currentFloorX, GROUND_TOP, CANVAS_WIDTH - currentFloorX, GROUND_HEIGHT);
        c.strokeRect(currentFloorX, GROUND_TOP, CANVAS_WIDTH - currentFloorX, GROUND_HEIGHT);
    }
    
    // Draw player (QR code)
    c.save();
    c.translate(player.value.x, player.value.y);
    c.rotate(player.value.rotation);
    
    if (qrCodeImage.value) {
        // Draw QR code image
        c.drawImage(
            qrCodeImage.value,
            -PLAYER_SIZE / 2,
            -PLAYER_SIZE / 2,
            PLAYER_SIZE,
            PLAYER_SIZE
        );
    } else {
        // Fallback: draw square placeholder
        c.fillStyle = '#8b5cf6';
        c.strokeStyle = '#7c3aed';
        c.lineWidth = 2;
        c.fillRect(-PLAYER_SIZE / 2, -PLAYER_SIZE / 2, PLAYER_SIZE, PLAYER_SIZE);
        c.strokeRect(-PLAYER_SIZE / 2, -PLAYER_SIZE / 2, PLAYER_SIZE, PLAYER_SIZE);
        
        // Draw QR code pattern placeholder
        c.fillStyle = '#000000';
        const cellSize = PLAYER_SIZE / 8;
        for (let i = 0; i < 8; i++) {
            for (let j = 0; j < 8; j++) {
                if ((i + j) % 3 === 0 || (i === 0 || i === 7 || j === 0 || j === 7)) {
                    c.fillRect(
                        -PLAYER_SIZE / 2 + i * cellSize,
                        -PLAYER_SIZE / 2 + j * cellSize,
                        cellSize,
                        cellSize
                    );
                }
            }
        }
    }
    
    c.restore();
    
    // Draw level indicator
    c.fillStyle = 'rgba(0, 0, 0, 0.5)';
    c.fillRect(10, 10, 100, 30);
    c.fillStyle = '#ffffff';
    c.font = 'bold 16px Arial';
    c.fillText(`Level ${currentLevel.value}`, 15, 30);
    
    // Draw jumps remaining indicator
    c.fillStyle = 'rgba(0, 0, 0, 0.5)';
    c.fillRect(10, 45, 120, 25);
    c.fillStyle = jumpsRemaining.value > 0 ? '#10b981' : '#ef4444';
    c.font = 'bold 14px Arial';
    c.fillText(`Jumps: ${jumpsRemaining.value}`, 15, 63);
    
    // Draw distance/time for level 3
    if (currentLevel.value === 3 && startTime.value) {
        const timeSurvived = Math.floor((Date.now() - startTime.value) / 1000);
        c.fillStyle = 'rgba(0, 0, 0, 0.5)';
        c.fillRect(10, 75, 150, 25);
        c.fillStyle = '#fbbf24';
        c.font = 'bold 14px Arial';
        c.fillText(`Time: ${timeSurvived}s`, 15, 93);
    }
    
    // Draw level completion overlay
    if (showLevelComplete.value) {
        c.fillStyle = 'rgba(0, 0, 0, 0.8)';
        c.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        
        c.fillStyle = '#10b981';
        c.font = 'bold 32px Arial';
        c.textAlign = 'center';
        c.fillText('Level Complete!', CANVAS_WIDTH / 2, CANVAS_HEIGHT / 2 - 40);
        
        c.fillStyle = '#ffffff';
        c.font = 'bold 18px Arial';
        c.fillText(`Level ${currentLevel.value} Finished!`, CANVAS_WIDTH / 2, CANVAS_HEIGHT / 2);
        
        if (currentLevel.value < 3) {
            c.fillStyle = '#fbbf24';
            c.font = '16px Arial';
            c.fillText('Tap to Start Level ' + (currentLevel.value + 1), CANVAS_WIDTH / 2, CANVAS_HEIGHT / 2 + 40);
        } else {
            c.fillStyle = '#fbbf24';
            c.font = '16px Arial';
            c.fillText('Tap to Start Endless Mode', CANVAS_WIDTH / 2, CANVAS_HEIGHT / 2 + 40);
        }
        
        c.textAlign = 'left'; // Reset alignment
    }
};

// Update function
const update = () => {
    if (props.gameState !== 'playing' || levelCompleted.value) {
        // Still draw even when paused
        draw();
        if (levelCompleted.value) {
            animationFrame.value = requestAnimationFrame(update);
        }
        return;
    }
    
    backgroundOffset.value += gameSpeed.value;

    // Move obstacles
    obstacles.value.forEach(obstacle => {
        obstacle.x -= gameSpeed.value;
    });

    // Move pits
    pits.value.forEach(pit => {
        pit.x -= gameSpeed.value;
    });

    // Apply gravity
    player.value.velocity += GRAVITY;
    player.value.y += player.value.velocity;

    // Check if player fell into a pit or out of bounds
    if (player.value.y > CANVAS_HEIGHT + PLAYER_SIZE) {
        const gameData = {
            level: currentLevel.value,
            distance: distanceTraveled.value,
            timeSurvived: currentLevel.value === 3 && startTime.value
                ? Math.floor((Date.now() - startTime.value) / 1000)
                : null,
        };
        props.endGame(gameData);
        return;
    }

    // Ground collision - reset jumps when touching ground
    let playerOnAnyGround = false;
    
    // Check if on ground (not in a pit)
    if (player.value.y >= GROUND_TOP - PLAYER_SIZE / 2) {
        const playerX = player.value.x;
        const inPit = pits.value.find(pit => playerX > pit.x && playerX < pit.x + pit.width);
        
        if (!inPit) {
        player.value.y = GROUND_TOP - PLAYER_SIZE / 2;
        player.value.velocity = 0;
            playerOnAnyGround = true;
        }
    }

    // Check platform collisions (jumping on blocks)
    obstacles.value.forEach(obstacle => {
        // Score for passing obstacles (levels 1-2 only)
        if (!obstacle.passed && obstacle.x + obstacle.width < player.value.x) {
            obstacle.passed = true;
            if (currentLevel.value < 3) {
                props.addScore(50);
            }
        }

        // Collision detection (AABB)
        const playerLeft = player.value.x - PLAYER_SIZE / 2;
        const playerRight = player.value.x + PLAYER_SIZE / 2;
        const playerTop = player.value.y - PLAYER_SIZE / 2;
        const playerBottom = player.value.y + PLAYER_SIZE / 2;

        if (playerRight > obstacle.x &&
            playerLeft < obstacle.x + obstacle.width &&
            playerBottom > obstacle.y &&
            playerTop < obstacle.y + obstacle.height) {
            
            if (obstacle.type === 'spike') {
                // Spikes are always lethal
                const gameData = {
                    level: currentLevel.value,
                    distance: distanceTraveled.value,
                    timeSurvived: currentLevel.value === 3 && startTime.value
                        ? Math.floor((Date.now() - startTime.value) / 1000)
                        : null,
                };
                props.endGame(gameData);
            } else if (obstacle.type === 'block') {
                // Blocks: land on top if falling, otherwise lethal
                const isFallingIntoTop = player.value.velocity >= 0 && 
                                       playerBottom <= obstacle.y + 15; // 15px buffer for "landing"
                
                if (isFallingIntoTop) {
                    player.value.y = obstacle.y - PLAYER_SIZE / 2;
                    player.value.velocity = 0;
                    playerOnAnyGround = true;
                } else {
                    // Hit side or bottom
            const gameData = {
                level: currentLevel.value,
                distance: distanceTraveled.value,
                timeSurvived: currentLevel.value === 3 && startTime.value
                    ? Math.floor((Date.now() - startTime.value) / 1000)
                    : null,
            };
            props.endGame(gameData);
        }
            }
        }
    });

    // Update rotation (spin when jumping, reset when on ground)
    if (player.value.velocity < 0) {
        player.value.rotation += 0.3;
        if (player.value.rotation >= Math.PI * 2) player.value.rotation -= Math.PI * 2;
    } else if (playerOnAnyGround) {
        player.value.rotation = 0;
    } else {
        player.value.rotation += 0.1;
        if (player.value.rotation >= Math.PI * 2) player.value.rotation -= Math.PI * 2;
    }

    if (playerOnAnyGround) {
        if (!isOnGround.value) {
            // Just landed - reset jumps
            jumpsRemaining.value = 2;
            isOnGround.value = true;
        }
    } else {
        isOnGround.value = false;
    }

    // Ceiling collision
    if (player.value.y <= PLAYER_SIZE / 2) {
        player.value.y = PLAYER_SIZE / 2;
        player.value.velocity = 0;
    }

    // Remove off-screen obstacles and pits
    obstacles.value = obstacles.value.filter(o => o.x > -100);
    pits.value = pits.value.filter(p => p.x > -100);

    // Update distance
    distanceTraveled.value += gameSpeed.value;

    // Endless mode: score is time survived (seconds)
    if (currentLevel.value === 3 && startTime.value) {
        const elapsedSeconds = Math.floor((Date.now() - startTime.value) / 1000);
        if (elapsedSeconds > lastSurvivalSecondScored.value) {
            props.addScore(elapsedSeconds - lastSurvivalSecondScored.value);
            lastSurvivalSecondScored.value = elapsedSeconds;
        }
    }

    // Check level completion (Level 1 and 2)
    if (currentLevel.value < 3 && !levelCompleted.value) {
        const remainingObstacles = obstacles.value.filter(o => !o.passed);
        if (remainingObstacles.length === 0) {
            // Level completed - pause and show completion screen
            levelCompleted.value = true;
            showLevelComplete.value = true;
            // Don't auto-advance - wait for player click
        }
    } else {
        // Level 3: Add more obstacles procedurally + ramp difficulty
        addMoreObstacles();
        if (distanceTraveled.value >= nextSpeedIncreaseAt.value) {
            gameSpeed.value += 0.15;
            nextSpeedIncreaseAt.value += 2500;
        }
    }
    
    draw();
    animationFrame.value = requestAnimationFrame(update);
};

// Watch game state
watch(() => props.gameState, (state) => {
    if (state === 'playing') {
        currentLevel.value = 1;
        generateQRCode();
        initGame();
        ctx.value = canvas.value?.getContext('2d');
        update();
    } else if (animationFrame.value) {
        cancelAnimationFrame(animationFrame.value);
    }
});

onMounted(() => {
    generateQRCode();
});

onUnmounted(() => {
    if (animationFrame.value) {
        cancelAnimationFrame(animationFrame.value);
    }
});
</script>

<template>
    <div class="flex flex-col items-center justify-center p-4">
        <div class="mb-4 text-center">
            <span class="text-white">
                Level {{ currentLevel }} 
                <span v-if="currentLevel < 3" class="text-purple-400">• Distance: {{ Math.floor(distanceTraveled) }}</span>
                <span v-else class="text-amber-400">• Endless Mode</span>
            </span>
        </div>
        
        <canvas
            ref="canvas"
            :width="CANVAS_WIDTH"
            :height="CANVAS_HEIGHT"
            class="rounded-lg border border-white/10 cursor-pointer w-full max-w-[420px] h-auto"
            @click="jump"
            @touchstart.prevent="jump"
        ></canvas>
        
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm">
            Tap to jump! Avoid the obstacles!
        </div>
        <div v-if="gameState === 'playing' && currentLevel < 3" class="mt-4 text-center text-gray-400 text-xs">
            Complete the level to advance!
        </div>
    </div>
</template>

