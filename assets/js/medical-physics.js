/**
 * MedAI Physics Floating & Stacking Icons Engine (Retina Ready)
 */
class MedicalPhysics {
    constructor(containerSelector) {
        this.container = document.querySelector(containerSelector);
        if (!this.container) return;

        // ВАЖНО: Уничтожаем предыдущий экземпляр при повторной инициализации
        if (window.medicalPhysicsInstance && typeof window.medicalPhysicsInstance.destroy === 'function') {
            window.medicalPhysicsInstance.destroy();
        }
        window.medicalPhysicsInstance = this;

        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d');
        this.canvas.style.position = 'absolute';
        this.canvas.style.top = '0';
        this.canvas.style.left = '0';
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        this.canvas.style.pointerEvents = 'none';
        this.canvas.style.zIndex = '1'; 
        this.container.appendChild(this.canvas);

        const centerHeader = this.container.querySelector('.empty-center-header');
        if (centerHeader) {
            centerHeader.style.position = 'relative';
            centerHeader.style.zIndex = '2';
        }

        this.items = [];
        this.gravity = 0.015; 
        this.floorY = 0;
        this.isTrapdoorOpen = false;
        this.animationFrameId = null;
        this.spawnInterval = null;
        this.isPageActive = true; 

        this.maxItems = 35; 

        const iconColor = '#e2e8f0';
        this.shapes = [
            // 1. Сердце (Кардиология)
            { path: new Path2D('M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z'), color: iconColor, size: 24 },
            // 2. Зуб (Стоматология)
            { path: new Path2D('M19.34 7.82c-.52-2.18-2.31-3.69-4.73-3.69-.97 0-1.84.28-2.61.81-.77-.53-1.64-.81-2.61-.81-2.42 0-4.21 1.51-4.73 3.69-.65 2.7.35 5.56 1.34 7.63.4.83.67 1.74.83 2.67.14.77.34 2.89 1.15 3.52.54.42 1.3.36 1.75-.15.73-.83 1.1-2.15 1.27-3.68.04-.37.36-.65.73-.65s.69.28.73.65c.17 1.53.54 2.85 1.27 3.68.45.51 1.21.57 1.75.15.81-.63 1.01-2.75 1.15-3.52.16-.93.43-1.84.83-2.67.99-2.07 1.99-4.93 1.34-7.63z'), color: iconColor, size: 22 },
            // 3. Шприц (Процедурный блок)
            { path: new Path2D('M19 7h-1V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v3H5a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h1v8H5a1 1 0 0 0 0 2h14a1 1 0 0 0 0-2h-1v-8h1a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1zm-3 0H8V4h8v3z M6 9h12v2H6V9zm5 14h2v2h-2v-2z'), color: iconColor, size: 22 },
            // 4. Красный крест (Скорая помощь)
            { path: new Path2D('M10.5 4h3v6.5H20v3h-6.5V20h-3v-6.5H4v-3h6.5V4z'), color: iconColor, size: 24 },
            // 5. Капсулы / Таблетки (Фармакология)
            { path: new Path2D('M6 3h12a3 3 0 0 1 3 3v2a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3z M6 13h12a3 3 0 0 1 3 3v2a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2a3 3 0 0 1 3-3z'), color: iconColor, size: 20 },
            // 6. Лабораторная колба (Биохимия)
            { path: new Path2D('M16 2H8v2h2v5.17L4.29 17.2A2 2 0 0 0 6 20h12a2 2 0 0 0 1.71-2.8L14 9.17V4h2V2z'), color: iconColor, size: 22 }
        ];

        this.boundResize = () => this.resize();
        this.boundVisibilityChange = () => this.handleVisibilityChange();

        window.addEventListener('resize', this.boundResize);
        document.addEventListener('visibilitychange', this.boundVisibilityChange);

        this.resize();
        this.startSpawning();
        this.loop();
    }

    destroy() {
        this.isPageActive = false;
        this.isTrapdoorOpen = true;
        if (this.spawnInterval) {
            clearInterval(this.spawnInterval);
            this.spawnInterval = null;
        }
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
        if (this.boundResize) {
            window.removeEventListener('resize', this.boundResize);
        }
        if (this.boundVisibilityChange) {
            document.removeEventListener('visibilitychange', this.boundVisibilityChange);
        }
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.remove();
        }
        if (window.medicalPhysicsInstance === this) {
            window.medicalPhysicsInstance = null;
        }
    }

    resize() {
        const dpr = window.devicePixelRatio || 1;
        const rect = this.container.getBoundingClientRect();

        this.canvas.width = rect.width * dpr;
        this.canvas.height = rect.height * dpr;
        this.canvas.style.width = `${rect.width}px`;
        this.canvas.style.height = `${rect.height}px`;

        this.ctx.scale(dpr, dpr);

        this.logicalWidth = rect.width;
        this.logicalHeight = rect.height;
        this.floorY = this.logicalHeight;
    }

    handleVisibilityChange() {
        if (document.hidden) {
            this.isPageActive = false;
            if (this.spawnInterval) {
                clearInterval(this.spawnInterval);
                this.spawnInterval = null;
            }
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }
        } else {
            if (this.isTrapdoorOpen) return;
            this.isPageActive = true;
            if (!this.spawnInterval) this.startSpawning();
            if (!this.animationFrameId) this.loop();
        }
    }

    startSpawning() {
        this.spawnInterval = setInterval(() => {
            if (this.isTrapdoorOpen) {
                clearInterval(this.spawnInterval);
                this.spawnInterval = null;
                return;
            }
            if (this.items.length < this.maxItems) {
                this.spawnItem();
            }
        }, 300);
    }

    spawnItem() {
        if (this.isTrapdoorOpen) return; 

        const shapeTemplate = this.shapes[Math.floor(Math.random() * this.shapes.length)];
        const scale = this.logicalWidth > 768 ? 1 : 0.7; 

        this.items.push({
            x: Math.random() * this.logicalWidth,
            y: -30,
            vy: Math.random() * 0.8 + 0.4,
            vx: 0,
            baseX: Math.random() * this.logicalWidth, 
            driftOffset: Math.random() * Math.PI * 2, 
            driftSpeed: Math.random() * 0.0015 + 0.0005, 
            driftWidth: Math.random() * 40 + 20, 
            angle: Math.random() * Math.PI * 2,
            va: (Math.random() - 0.5) * 0.015, 
            shape: shapeTemplate.path,
            color: shapeTemplate.color,
            size: shapeTemplate.size * scale, 
            scale: scale
        });
    }

    loop() {
        if (!this.isPageActive) return;

        this.ctx.clearRect(0, 0, this.logicalWidth, this.logicalHeight);

        for (let i = 0; i < this.items.length; i++) {
            let item = this.items[i];

            if (!this.isTrapdoorOpen) {
                // Плавный постоянный полет сверху вниз без складывания внизу
                item.y += item.vy;
                item.x = item.baseX + Math.sin(Date.now() * item.driftSpeed + item.driftOffset) * item.driftWidth;
                item.angle += item.va;

                // Зацикливание: при выходе за нижнюю границу возвращается наверх
                if (item.y > this.logicalHeight + 30) {
                    item.y = -30;
                    item.baseX = Math.random() * this.logicalWidth;
                    item.vy = Math.random() * 0.8 + 0.4;
                }
            } else {
                // При отправке сообщения — улетают вниз с ускорением
                item.vy += 0.8;
                item.y += item.vy;
                item.x += item.vx;
                item.angle += item.va * 3;
            }

            this.ctx.save();
            this.ctx.translate(item.x, item.y);
            this.ctx.rotate(item.angle);
            this.ctx.scale(item.scale, item.scale);
            this.ctx.translate(-12, -12); 
            this.ctx.fillStyle = item.color;
            this.ctx.globalAlpha = 0.22; 
            this.ctx.fill(item.shape);
            this.ctx.restore();
        }

        // Проверка завершения ухода всех предметов под пол
        if (this.isTrapdoorOpen && (this.items.length === 0 || this.items.every(item => item.y > this.logicalHeight + 50))) {
            this.ctx.clearRect(0, 0, this.logicalWidth, this.logicalHeight);
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }
            if (this.spawnInterval) {
                clearInterval(this.spawnInterval);
                this.spawnInterval = null;
            }
            this.canvas.remove();
            if (window.medicalPhysicsInstance === this) {
                window.medicalPhysicsInstance = null;
            }
            return;
        }

        this.animationFrameId = requestAnimationFrame(() => this.loop());
    }

    openTrapdoor() {
        this.isTrapdoorOpen = true;
        if (this.spawnInterval) {
            clearInterval(this.spawnInterval);
            this.spawnInterval = null;
        }

        if (this.items.length === 0) {
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }
            this.canvas.remove();
            if (window.medicalPhysicsInstance === this) {
                window.medicalPhysicsInstance = null;
            }
            return;
        }

        this.items.forEach(item => {
            item.vy = Math.random() * 6 + 10; 
            item.vx = (Math.random() - 0.5) * 2; 
            item.va = (Math.random() - 0.5) * 0.2; 
        });
    }
}