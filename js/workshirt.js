class Slider {
    constructor() {
      this.bindAll()
      
      this.vert = `
      varying vec2 vUv;
      void main() {
        vUv = uv;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
      }
      `
  
      this.frag = `
      varying vec2 vUv;
  
      uniform sampler2D texture1;
      uniform sampler2D texture2;
      uniform sampler2D disp;
  
      uniform float dispPower;
      uniform float intensity;
  
      uniform vec2 size;
      uniform vec2 res;
      uniform float isMobile;
      uniform float isSlide1;
      uniform float cropHorizontal; // 0.0 = left, 0.5 = center, 1.0 = right
      uniform float cropVertical;   // 0.0 = top, 0.5 = center, 1.0 = bottom

      vec2 backgroundCoverUv( vec2 screenSize, vec2 imageSize, vec2 uv, float horizontalOffset, float verticalOffset ) {
        float screenRatio = screenSize.x / screenSize.y;
        float imageRatio = imageSize.x / imageSize.y;
        vec2 newSize = screenRatio < imageRatio 
            ? vec2(imageSize.x * (screenSize.y / imageSize.y), screenSize.y)
            : vec2(screenSize.x, imageSize.y * (screenSize.x / imageSize.x));
        vec2 newOffset = (screenRatio < imageRatio 
            ? vec2((newSize.x - screenSize.x) * horizontalOffset, 0.0) 
            : vec2(0.0, (newSize.y - screenSize.y) * verticalOffset)) / newSize;
        return uv * screenSize / newSize + newOffset;
      }

      void main() {
        vec2 uv = vUv;
        
        vec4 disp = texture2D(disp, uv);
        vec2 dispVec = vec2(disp.x, disp.y);
        
        vec2 distPos1 = uv + (dispVec * intensity * dispPower);
        vec2 distPos2 = uv + (dispVec * -(intensity * (1.0 - dispPower)));
        
        // Use crop (cover) instead of shrink on all devices for all slides
        vec2 finalUv1 = backgroundCoverUv(res, size, distPos1, cropHorizontal, cropVertical);
        
        vec4 _texture1 = texture2D(texture1, finalUv1);
        vec4 _texture2 = texture2D(texture2, distPos2);
        
        gl_FragColor = mix(_texture1, _texture2, dispPower);
      }
      `
      
      this.el = document.querySelector('.js-slider')
      this.inner = this.el.querySelector('.js-slider__inner')
      this.slides = [...this.el.querySelectorAll('.js-slide')]
      this.bullets = [...this.el.querySelectorAll('.js-slider-bullet')]
      
      // Ensure exactly 3 slides for WebGL morph effect
      if (this.slides.length !== 3) {
        console.warn(`Expected exactly 3 slides for WebGL morph, found ${this.slides.length}`)
      }
      
      // Verify all slides have required structure
      this.slides.forEach((slide, index) => {
        const content = slide.querySelector('.slide__content')
        const images = slide.querySelectorAll('.js-slide__img')
        const text = slide.querySelector('.js-slider__text')
        
        if (!content) {
          console.error(`Slide ${index + 1} is missing .slide__content`)
        }
        if (!images || images.length === 0) {
          console.error(`Slide ${index + 1} is missing .js-slide__img elements`)
        }
        if (!text) {
          console.error(`Slide ${index + 1} is missing .js-slider__text`)
        }
      })
      
      this.renderer = null
      this.scene = null
      this.clock = null
      this.camera = null
      this.mesh = null
      
      // Use local morph SVG images for WebGL morphing background
      this.images = [
        'images/morph1.svg',  // Slide 1 background
        'images/morph2.svg',  // Slide 2 background
        'images/morph3.svg'   // Slide 3 background
      ]
      
      // Ensure exactly 3 images for 3 slides
      if (this.images.length !== 3) {
        console.warn(`Expected exactly 3 images for WebGL morph, found ${this.images.length}`)
        // Truncate or pad to ensure exactly 3
        if (this.images.length > 3) {
          this.images = this.images.slice(0, 3)
        } else if (this.images.length < 3) {
          while (this.images.length < 3) {
            this.images.push(this.images[this.images.length - 1] || this.images[0])
          }
        }
      }
      
      this.data = {
        current: 0,
        next: 1,
        total: 2, // Exactly 3 slides (indices 0, 1, 2)
        delta: 0
      }
      
      // Crop position configuration for each slide
      // horizontal: 0.0 = left, 0.5 = center, 1.0 = right
      // vertical: 0.0 = top, 0.5 = center, 1.0 = bottom
      this.cropSettings = [
        { horizontal: 0.2, vertical: 0.0 }, // Slide 1
        { horizontal: 0.7, vertical: 0.0 }, // Slide 2
        { horizontal: 0.5, vertical: 0.5 }    // Slide 3
      ]
      
      this.state = {
        animating: false,
        text: false,
        initial: true
      }
      
      this.textures = null
      
      this.init()
    }
    
    bindAll() {
      ['render', 'nextSlide']
      .forEach(fn => this[fn] = this[fn].bind(this))
    }
    
    setStyles() {
      this.slides.forEach((slide, index) => {
        if (index === 0) {
          // Set initial styles for first slide - make it visible
          gsap.set(slide, { autoAlpha: 1 })
          const content = slide.querySelector('.slide__content')
          const text = slide.querySelector('.js-slider__text')
          const textLines = slide.querySelectorAll('.js-slider__text-line div')
          const images = slide.querySelectorAll('.js-slide__img')
          
          if (content) gsap.set(content, { xPercent: 0, opacity: 1 })
          if (text) gsap.set(text, { xPercent: 0, opacity: 1 })
          textLines.forEach(line => gsap.set(line, { xPercent: 0, opacity: 1 }))
          images.forEach(img => gsap.set(img, { xPercent: 0, opacity: 1, scale: 1 }))
        } else {
          gsap.set(slide, { autoAlpha: 0 })
          // Set initial styles for other slides - prepare for horizontal entrance
          const content = slide.querySelector('.slide__content')
          const text = slide.querySelector('.js-slider__text')
          const textLines = slide.querySelectorAll('.js-slider__text-line div')
          const images = slide.querySelectorAll('.js-slide__img')
          
          if (content) gsap.set(content, { xPercent: 100, opacity: 0 })
          if (text) gsap.set(text, { xPercent: 30, opacity: 0 })
          textLines.forEach(line => gsap.set(line, { xPercent: 20, opacity: 0 }))
          images.forEach(img => gsap.set(img, { xPercent: 100, opacity: 0, scale: 0.9 }))
        }
      })
      
      this.bullets.forEach((bullet, index) => {
        if (index === 0) return
        
        const txt = bullet.querySelector('.js-slider-bullet__text')
        const line = bullet.querySelector('.js-slider-bullet__line')
        
        gsap.set(txt, {
          opacity: 0.25
        })
        gsap.set(line, {
          scaleX: 0,
          transformOrigin: 'left'
        })
      })
    }
    
    cameraSetup() {
      this.camera = new THREE.OrthographicCamera(
        this.el.offsetWidth / -2,
        this.el.offsetWidth / 2,
        this.el.offsetHeight / 2,
        this.el.offsetHeight / -2,
        1,
        1000
      )
  
      this.camera.lookAt(this.scene.position)
      this.camera.position.z = 1
    }
  
    setup() {
      this.scene = new THREE.Scene()
      this.clock = new THREE.Clock(true)
      
      this.renderer = new THREE.WebGLRenderer({ 
        alpha: true,
        antialias: true 
      })
      this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))
      this.renderer.setSize(window.innerWidth, window.innerHeight)
      
      // Ensure canvas follows the viewport
      this.renderer.domElement.style.position = 'fixed'
      this.renderer.domElement.style.top = '0'
      this.renderer.domElement.style.left = '0'
      this.renderer.domElement.style.width = '100%'
      this.renderer.domElement.style.height = '100%'
      this.renderer.domElement.style.pointerEvents = 'none'
      this.renderer.domElement.style.zIndex = '1'
      
      this.inner.appendChild(this.renderer.domElement)
    }
    
    loadTextures() {
      const loader = new THREE.TextureLoader()
      loader.crossOrigin = 'anonymous'
      
      this.textures = []
      let loadedCount = 0
      let meshCreated = false
      const totalImages = this.images.length + 1 // +1 for displacement map
      
      const onTextureLoaded = () => {
        loadedCount++
        
        // Create mesh as soon as we have at least 2 textures and displacement map
        // Don't wait for all textures - start rendering immediately
        if (!meshCreated && loadedCount >= 2 && this.disp && this.textures.length >= 2) {
          this.createMesh()
          this.render()
          meshCreated = true
        }
        
        // Update mesh if it already exists and we get more textures
        if (meshCreated && this.mat && loadedCount <= totalImages) {
          // Update texture2 if we have a second texture
          if (this.textures.length >= 2 && this.mat.uniforms.texture2) {
            this.mat.uniforms.texture2.value = this.textures[1]
          }
          this.render()
        }
      }
      
      // Preload images using HTML Image for faster loading
      const preloadImages = this.images.map((imageSrc, index) => {
        return new Promise((resolve, reject) => {
          const img = new Image()
          img.crossOrigin = 'anonymous'
          img.onload = () => {
            // Once image is loaded, create texture from it
            const texture = loader.load(
              imageSrc,
              (loadedTexture) => {
                // Optimize texture settings for performance
                loadedTexture.minFilter = THREE.LinearFilter
                loadedTexture.magFilter = THREE.LinearFilter
                loadedTexture.generateMipmaps = false
                loadedTexture.flipY = false
                loadedTexture.anisotropy = 1 // Lower anisotropy for speed
                this.textures[index] = loadedTexture
                onTextureLoaded()
                resolve(loadedTexture)
              },
              undefined,
              (error) => {
                console.error('Error loading texture:', error, 'for image:', imageSrc)
                // Create smaller fallback texture for speed
                const size = 256 // Smaller fallback for faster loading
                const data = new Uint8Array(size * size * 4)
                for (let i = 0; i < size * size; i++) {
                  const stride = i * 4
                  const value = 20
                  data[stride] = value
                  data[stride + 1] = value
                  data[stride + 2] = value
                  data[stride + 3] = 255
                }
                const fallbackTexture = new THREE.DataTexture(data, size, size, THREE.RGBAFormat)
                fallbackTexture.needsUpdate = true
                fallbackTexture.minFilter = THREE.LinearFilter
                fallbackTexture.magFilter = THREE.LinearFilter
                this.textures[index] = fallbackTexture
                onTextureLoaded()
                resolve(fallbackTexture)
              }
            )
          }
          img.onerror = reject
          img.src = imageSrc // Start loading immediately
        })
      })
      
      // Load all images in parallel
      Promise.all(preloadImages).catch(err => {
        console.error('Error preloading images:', err)
      })
      
      // Load displacement map (can load in parallel with images)
      this.disp = loader.load(
        'https://s3-us-west-2.amazonaws.com/s.cdpn.io/58281/rock-_disp.png',
        (loadedDisp) => {
          // Set properties after loading
          loadedDisp.magFilter = loadedDisp.minFilter = THREE.LinearFilter
          loadedDisp.wrapS = loadedDisp.wrapT = THREE.RepeatWrapping
          loadedDisp.generateMipmaps = false
          onTextureLoaded()
          if (meshCreated) {
            this.render()
          }
        },
        undefined,
        (error) => {
          console.error('Error loading displacement map:', error)
          // Create a simple fallback texture (faster, smaller)
          const size = 64 // Smaller for speed
          const data = new Uint8Array(size * size * 4)
          for (let i = 0; i < size * size; i++) {
            const stride = i * 4
            data[stride] = 128
            data[stride + 1] = 128
            data[stride + 2] = 128
            data[stride + 3] = 255
          }
          this.disp = new THREE.DataTexture(data, size, size, THREE.RGBAFormat)
          this.disp.needsUpdate = true
          this.disp.minFilter = THREE.LinearFilter
          this.disp.magFilter = THREE.LinearFilter
          this.disp.wrapS = this.disp.wrapT = THREE.RepeatWrapping
          onTextureLoaded()
        }
      )
    }
    
    createMesh() {
      if (!this.textures || this.textures.length < 2 || !this.disp) {
        console.error('Textures not ready for mesh creation')
        return
      }
      
      // Detect if mobile (viewport width <= 768px)
      const isMobile = window.innerWidth <= 768 ? 1.0 : 0.0
      // Check if current slide is slide 1 (index 0)
      const isSlide1 = this.data.current === 0 ? 1.0 : 0.0
      
      // Get current slide's crop settings
      const currentCrop = this.cropSettings[this.data.current] || { horizontal: 0.5, vertical: 0.5 }
      
      this.mat = new THREE.ShaderMaterial( { 
        uniforms: {
          dispPower: { type: 'f', value: 0.0 },
          intensity: { type: 'f', value: 0.5 },
          res: { value: new THREE.Vector2(window.innerWidth, window.innerHeight) },
          size: { value: new THREE.Vector2(1, 1) },
          texture1: { type: 't', value: this.textures[0] },
          texture2: { type: 't', value: this.textures[1] || this.textures[0] },
          disp: { type: 't', value: this.disp },
          isMobile: { type: 'f', value: isMobile },
          isSlide1: { type: 'f', value: isSlide1 },
          cropHorizontal: { type: 'f', value: currentCrop.horizontal },
          cropVertical: { type: 'f', value: currentCrop.vertical }
        },
        transparent: true,
        vertexShader: this.vert,
        fragmentShader: this.frag
      })
  
      // Use PlaneGeometry for newer Three.js versions, fallback to PlaneBufferGeometry
      const Geometry = THREE.PlaneGeometry || THREE.PlaneBufferGeometry
      
      // Calculate geometry dimensions to fit screen width
      const screenWidth = window.innerWidth
      const screenHeight = window.innerHeight
      const aspectRatio = screenHeight / screenWidth
      
      // Create geometry that fits full screen width
      const geometry = new Geometry(
        screenWidth, 
        screenHeight, 
        1
      )
      
      this.mesh = new THREE.Mesh(geometry, this.mat)
      
      // Flip image upside down - rotate 180 degrees on Z axis
      this.mesh.rotation.z = Math.PI // 180 degrees in radians
      
      // Flip image from right to left (horizontal flip) - scale X axis to -1
      this.mesh.scale.x = -1
      
      this.scene.add(this.mesh)
      
      // Update texture size in uniforms and resolution
      if (this.textures[0].image) {
        this.mat.uniforms.size.value = new THREE.Vector2(
          this.textures[0].image.naturalWidth || this.textures[0].image.width,
          this.textures[0].image.naturalHeight || this.textures[0].image.height
        )
      }
      
      // Update resolution uniform for proper scaling
      this.mat.uniforms.res.value = new THREE.Vector2(screenWidth, screenHeight)
      
      // Update isMobile and isSlide1 uniforms
      this.mat.uniforms.isMobile.value = isMobile
      this.mat.uniforms.isSlide1.value = isSlide1
    }
    
    transitionNext() {
      const current = this.slides[this.data.current]
      const next = this.slides[this.data.next]
      
      // Start WebGL morph effect synchronized with slide transition
      if (this.mat) {
        // Update texture2 to next slide's image
        const nextImageIndex = this.data.next
        if (this.textures[nextImageIndex]) {
          this.mat.uniforms.texture2.value = this.textures[nextImageIndex]
        }
        
        // Update isSlide1 uniform based on current slide (texture1)
        this.mat.uniforms.isSlide1.value = this.data.current === 0 ? 1.0 : 0.0
        
        gsap.to(this.mat.uniforms.dispPower, {
          value: 1,
          duration: 1.8,
          ease: "power3.inOut",
          onUpdate: () => {
            if (this.renderer && this.scene && this.camera) {
              this.renderer.render(this.scene, this.camera)
            }
          },
          onComplete: () => {
            // Switch textures after morph completes
            this.mat.uniforms.texture1.value = this.textures[nextImageIndex]
            this.mat.uniforms.dispPower.value = 0.0
            // Update isSlide1 uniform based on the actual texture index (nextImageIndex), not this.data.next
            // because this.data.next may have been updated by nextSlide() already
            this.mat.uniforms.isSlide1.value = nextImageIndex === 0 ? 1.0 : 0.0
            // Update crop settings for the new slide
            const newCrop = this.cropSettings[nextImageIndex] || { horizontal: 0.5, vertical: 0.5 }
            this.mat.uniforms.cropHorizontal.value = newCrop.horizontal
            this.mat.uniforms.cropVertical.value = newCrop.vertical
            // Update texture size uniform to match new texture
            if (this.textures[nextImageIndex] && this.textures[nextImageIndex].image) {
              this.mat.uniforms.size.value = new THREE.Vector2(
                this.textures[nextImageIndex].image.naturalWidth || this.textures[nextImageIndex].image.width,
                this.textures[nextImageIndex].image.naturalHeight || this.textures[nextImageIndex].image.height
              )
            }
            if (this.renderer && this.scene && this.camera) {
              this.renderer.render(this.scene, this.camera)
            }
          }
        })
      }
      
      const currentImages = current.querySelectorAll('.js-slide__img')
      const nextImages = next.querySelectorAll('.js-slide__img')
      
      const currentText = current.querySelectorAll('.js-slider__text-line div')
      const nextText = next.querySelectorAll('.js-slider__text-line div')
      
      const currentTextContainer = current.querySelector('.js-slider__text')
      const nextTextContainer = next.querySelector('.js-slider__text')
      
      const currentBullet = this.bullets[this.data.current]
      const nextBullet = this.bullets[this.data.next]
      
      const currentBulletTxt = currentBullet ? currentBullet.querySelector('.js-slider-bullet__text') : null
      const nextBulletTxt = nextBullet ? nextBullet.querySelector('.js-slider-bullet__text') : null
      
      const currentBulletLine = currentBullet ? currentBullet.querySelector('.js-slider-bullet__line') : null
      const nextBulletLine = nextBullet ? nextBullet.querySelector('.js-slider-bullet__line') : null
      
      const tl = gsap.timeline({ paused: true })
      
      if (this.state.initial) {
        gsap.to('.js-scroll', {
          opacity: 0,
          duration: 1.5,
          ease: "power2.inOut"
        })
        
        this.state.initial = false
      }
      
      // Get slide content container for horizontal animation
      const currentContent = current.querySelector('.slide__content')
      const nextContent = next.querySelector('.slide__content')
      
      tl
      // Animate current images and content horizontally - smooth exit
      .fromTo(currentImages, {
        xPercent: 0,
        scale: 1,
        opacity: 1
      }, {
        xPercent: -100,
        scale: 0.95,
        opacity: 0,
        duration: 1.8,
        ease: "power3.inOut",
        stagger: 0.08
      }, 0)
      // Animate current slide content container
      .fromTo(currentContent, {
        xPercent: 0,
        opacity: 1
      }, {
        xPercent: -100,
        opacity: 0,
        duration: 1.8,
        ease: "power3.inOut"
      }, 0)
      // Animate current text container and text lines
      .fromTo(currentTextContainer, {
        xPercent: 0,
        opacity: 1
      }, {
        xPercent: -30,
        opacity: 0,
        duration: 1.8,
        ease: "power3.inOut"
      }, 0)
      .fromTo(currentText, {
        xPercent: 0,
        opacity: 1
      }, {
        xPercent: -20,
        opacity: 0,
        duration: 1.8,
        ease: "power3.inOut",
        stagger: 0.06
      }, 0)
      .to(currentBulletTxt, {
        opacity: 0.25,
        duration: 1.8,
        ease: "none"
      }, 0)
      .set(currentBulletLine, {
        transformOrigin: 'right'
      }, 0)
      .to(currentBulletLine, {
        scaleX: 0,
        duration: 1.8,
        ease: "power3.inOut"
      }, 0)
      
      tl
      .set(current, {
        autoAlpha: 0
      })
      .set(next, {
        autoAlpha: 1
      }, 0.9)
      
      // Animate next images and content horizontally - smooth entrance
      tl
      .fromTo(nextImages, {
        xPercent: 100,
        scale: 0.95,
        opacity: 0
      }, {
        xPercent: 0,
        scale: 1,
        opacity: 1,
        duration: 1.8,
        ease: "power3.out",
        stagger: 0.08
      }, 0.9)
      // Animate next slide content container
      .fromTo(nextContent, {
        xPercent: 100,
        opacity: 0
      }, {
        xPercent: 0,
        opacity: 1,
        duration: 1.8,
        ease: "power3.out"
      }, 0.9)
      // Animate next text container and text lines
      .fromTo(nextTextContainer, {
        xPercent: 30,
        opacity: 0
      }, {
        xPercent: 0,
        opacity: 1,
        duration: 1.8,
        ease: "power3.out"
      }, 1.05)
      .fromTo(nextText, {
        xPercent: 20,
        opacity: 0
      }, {
        xPercent: 0,
        opacity: 1,
        duration: 1.8,
        ease: "power3.out",
        stagger: 0.06
      }, 1.1)
      .to(nextBulletTxt, {
        opacity: 1,
        duration: 1.8,
        ease: "none"
      }, 0.9)
      .set(nextBulletLine, {
        transformOrigin: 'left'
      }, 0.9)
      .to(nextBulletLine, {
        scaleX: 1,
        duration: 1.8,
        ease: "power3.inOut"
      }, 0.9)
      
      tl.play()
      
      // Mark animation as complete when timeline finishes
      tl.eventCallback("onComplete", () => {
        this.state.animating = false
        // Ensure isSlide1 is updated after transition completes
        if (this.mat) {
          this.mat.uniforms.isSlide1.value = this.data.current === 0 ? 1.0 : 0.0
        }
      })
    }
    
    transitionPrev() {
      const current = this.slides[this.data.current]
      const next = this.slides[this.data.next]
      
      // Start WebGL morph effect
      if (this.mat) {
        const nextImageIndex = this.data.next
        if (this.textures[nextImageIndex]) {
          this.mat.uniforms.texture2.value = this.textures[nextImageIndex]
        }
        
        // Update isSlide1 uniform based on current slide (texture1)
        this.mat.uniforms.isSlide1.value = this.data.current === 0 ? 1.0 : 0.0
        
        gsap.to(this.mat.uniforms.dispPower, {
          value: 1,
          duration: 1.8,
          ease: "power3.inOut",
          onUpdate: () => {
            if (this.renderer && this.scene && this.camera) {
              this.renderer.render(this.scene, this.camera)
            }
          },
          onComplete: () => {
            this.mat.uniforms.texture1.value = this.textures[nextImageIndex]
            this.mat.uniforms.dispPower.value = 0.0
            // Update isSlide1 uniform based on the actual texture index (nextImageIndex), not this.data.next
            // because this.data.next may have been updated by prevSlide() already
            this.mat.uniforms.isSlide1.value = nextImageIndex === 0 ? 1.0 : 0.0
            // Update crop settings for the new slide
            const newCrop = this.cropSettings[nextImageIndex] || { horizontal: 0.5, vertical: 0.5 }
            this.mat.uniforms.cropHorizontal.value = newCrop.horizontal
            this.mat.uniforms.cropVertical.value = newCrop.vertical
            // Update texture size uniform to match new texture
            if (this.textures[nextImageIndex] && this.textures[nextImageIndex].image) {
              this.mat.uniforms.size.value = new THREE.Vector2(
                this.textures[nextImageIndex].image.naturalWidth || this.textures[nextImageIndex].image.width,
                this.textures[nextImageIndex].image.naturalHeight || this.textures[nextImageIndex].image.height
              )
            }
            if (this.renderer && this.scene && this.camera) {
              this.renderer.render(this.scene, this.camera)
            }
          }
        })
      }
      
      const currentImages = current.querySelectorAll('.js-slide__img')
      const nextImages = next.querySelectorAll('.js-slide__img')
      const currentText = current.querySelectorAll('.js-slider__text-line div')
      const nextText = next.querySelectorAll('.js-slider__text-line div')
      const currentTextContainer = current.querySelector('.js-slider__text')
      const nextTextContainer = next.querySelector('.js-slider__text')
      const currentBullet = this.bullets[this.data.current]
      const nextBullet = this.bullets[this.data.next]
      const currentBulletTxt = currentBullet ? currentBullet.querySelector('.js-slider-bullet__text') : null
      const nextBulletTxt = nextBullet ? nextBullet.querySelector('.js-slider-bullet__text') : null
      const currentBulletLine = currentBullet ? currentBullet.querySelector('.js-slider-bullet__line') : null
      const nextBulletLine = nextBullet ? nextBullet.querySelector('.js-slider-bullet__line') : null
      const currentContent = current.querySelector('.slide__content')
      const nextContent = next.querySelector('.slide__content')
      
      const tl = gsap.timeline({ paused: true })
      
      // Reverse animation - current slides out to right, next comes from left
      tl
      .fromTo(currentImages, {
        xPercent: 0,
        scale: 1,
        opacity: 1
      }, {
        xPercent: 100,
        scale: 0.95,
        opacity: 0,
        duration: 1.8,
        ease: "power3.inOut",
        stagger: 0.08
      }, 0)
      .fromTo(currentContent, {
        xPercent: 0,
        opacity: 1
      }, {
        xPercent: 100,
        opacity: 0,
        duration: 1.8,
        ease: "power3.inOut"
      }, 0)
      .fromTo(currentTextContainer, {
        xPercent: 0,
        opacity: 1
      }, {
        xPercent: 30,
        opacity: 0,
        duration: 1.8,
        ease: "power3.inOut"
      }, 0)
      .fromTo(currentText, {
        xPercent: 0,
        opacity: 1
      }, {
        xPercent: 20,
        opacity: 0,
        duration: 1.8,
        ease: "power3.inOut",
        stagger: 0.06
      }, 0)
      .to(currentBulletTxt, {
        opacity: 0.25,
        duration: 1.8,
        ease: "none"
      }, 0)
      .set(currentBulletLine, {
        transformOrigin: 'left'
      }, 0)
      .to(currentBulletLine, {
        scaleX: 0,
        duration: 1.8,
        ease: "power3.inOut"
      }, 0)
      
      tl
      .set(current, {
        autoAlpha: 0
      })
      .set(next, {
        autoAlpha: 1
      }, 0.9)
      
      tl
      .fromTo(nextImages, {
        xPercent: -100,
        scale: 0.95,
        opacity: 0
      }, {
        xPercent: 0,
        scale: 1,
        opacity: 1,
        duration: 1.8,
        ease: "power3.out",
        stagger: 0.08
      }, 0.9)
      .fromTo(nextContent, {
        xPercent: -100,
        opacity: 0
      }, {
        xPercent: 0,
        opacity: 1,
        duration: 1.8,
        ease: "power3.out"
      }, 0.9)
      .fromTo(nextTextContainer, {
        xPercent: -30,
        opacity: 0
      }, {
        xPercent: 0,
        opacity: 1,
        duration: 1.8,
        ease: "power3.out"
      }, 1.05)
      .fromTo(nextText, {
        xPercent: -20,
        opacity: 0
      }, {
        xPercent: 0,
        opacity: 1,
        duration: 1.8,
        ease: "power3.out",
        stagger: 0.06
      }, 1.1)
      .to(nextBulletTxt, {
        opacity: 1,
        duration: 1.8,
        ease: "none"
      }, 0.9)
      .set(nextBulletLine, {
        transformOrigin: 'right'
      }, 0.9)
      .to(nextBulletLine, {
        scaleX: 1,
        duration: 1.8,
        ease: "power3.inOut"
      }, 0.9)
      
      tl.play()
      
      tl.eventCallback("onComplete", () => {
        this.state.animating = false
        // Ensure isSlide1 is updated after transition completes (for transitionPrev)
        if (this.mat) {
          this.mat.uniforms.isSlide1.value = this.data.current === 0 ? 1.0 : 0.0
        }
      })
    }
    
    prevSlide() {
      if (this.state.animating) {
        console.log('Already animating, skipping')
        return
      }
      
      if (!this.mat) {
        console.warn('Material not ready yet')
        return
      }
      
      console.log('Previous slide called', { current: this.data.current })
      
      this.state.animating = true
      
      // Update indices for previous slide
      // Loop through exactly 3 slides (0, 1, 2)
      this.data.next = this.data.current
      this.data.current = this.data.current <= 0 ? 2 : this.data.current - 1
      // Note: isSlide1 uniform is updated in the onComplete callback of transitionPrev()
      // after texture1 actually changes to the new slide
      
      this.transitionPrev()
    }
    
    nextSlide() {
      if (this.state.animating) {
        console.log('Already animating, skipping')
        return
      }
      
      if (!this.mat) {
        console.warn('Material not ready yet')
        return
      }
      
      console.log('Next slide called', { current: this.data.current, next: this.data.next })
      
      this.state.animating = true
      
      this.transitionNext()
      
      // Update indices after starting transition
      const oldCurrent = this.data.current
      this.data.current = this.data.next
      // Loop through exactly 3 slides (0, 1, 2)
      this.data.next = (this.data.current + 1) % 3
      // Note: isSlide1 uniform is updated in the onComplete callback of transitionNext()
      // after texture1 actually changes to the new slide
    }
    
    changeTexture() {
      this.mat.uniforms.texture1.value = this.textures[this.data.current]
      this.mat.uniforms.texture2.value = this.textures[this.data.next]
      // Update isSlide1 uniform based on current slide
      this.mat.uniforms.isSlide1.value = this.data.current === 0 ? 1.0 : 0.0
      // Update crop settings for current slide
      const currentCrop = this.cropSettings[this.data.current] || { horizontal: 0.5, vertical: 0.5 }
      this.mat.uniforms.cropHorizontal.value = currentCrop.horizontal
      this.mat.uniforms.cropVertical.value = currentCrop.vertical
    }
   
    listeners() {
      // Navigation buttons
      const prevBtn = document.querySelector('.js-slider-btn-prev')
      const nextBtn = document.querySelector('.js-slider-btn-next')
      
      if (prevBtn) {
        prevBtn.addEventListener('click', () => {
          if (!this.state.animating) {
            this.prevSlide()
          }
        })
      }
      
      if (nextBtn) {
        nextBtn.addEventListener('click', () => {
          if (!this.state.animating) {
            this.nextSlide()
          }
        })
      }
      
      // Bullet navigation
      this.bullets.forEach((bullet, index) => {
        bullet.addEventListener('click', () => {
          if (!this.state.animating && this.data.current !== index) {
            this.goToSlide(index)
          }
        })
      })
      
      // Keyboard support disabled - only button clicks control navigation
      // Uncomment below if you want keyboard navigation back:
      /*
      window.addEventListener('keydown', (e) => {
        if (this.state.animating) return
        
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
          e.preventDefault()
          this.nextSlide()
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
          e.preventDefault()
          this.prevSlide()
        }
      })
      */
      
      // Allow page scrolling - navigation is button-only, so no need to prevent touch events
      // Removed touchmove preventDefault to allow normal page scrolling on mobile
      
      // Update button states
      this.updateButtonStates()
      
      console.log('Navigation listeners initialized')
    }
    
    updateButtonStates() {
      const prevBtn = document.querySelector('.js-slider-btn-prev')
      const nextBtn = document.querySelector('.js-slider-btn-next')
      
      if (prevBtn && nextBtn) {
        // Disable prev on first slide, disable next on last slide (if needed)
        // For now, allow looping
        prevBtn.disabled = false
        nextBtn.disabled = false
      }
    }
    
    goToSlide(index) {
      // Ensure index is within bounds (0-2 for exactly 3 slides)
      if (index < 0 || index > 2) {
        console.warn(`Invalid slide index: ${index}. Must be between 0 and 2.`)
        return
      }
      
      if (this.state.animating || index === this.data.current) return
      
      this.state.animating = true
      
      // Calculate direction
      const direction = index > this.data.current ? 1 : -1
      
      // Update indices based on direction
      if (direction > 0) {
        // Going forward
        this.data.next = index
      } else {
        // Going backward
        this.data.next = this.data.current
        this.data.current = index
      }
      
      // Trigger appropriate transition based on direction
      if (direction > 0) {
        this.transitionNext()
      } else {
        this.transitionPrev()
      }
    }
    
    render() {
      this.renderer.render(this.scene, this.camera)
    }
    
    init() {
      // Check if required elements exist
      if (!this.el || !this.inner) {
        console.error('Slider elements not found')
        return
      }
      
      this.setup()
      this.cameraSetup()
      this.setStyles()
      
      // Load textures - mesh will be created when textures are loaded
      this.loadTextures()
      
      this.listeners()
      
      // Handle window resize
      window.addEventListener('resize', () => {
        if (this.renderer && this.camera && this.mat) {
          this.renderer.setSize(window.innerWidth, window.innerHeight)
          this.cameraSetup()
          // Update mobile detection and resolution
          const isMobile = window.innerWidth <= 768 ? 1.0 : 0.0
          this.mat.uniforms.isMobile.value = isMobile
          this.mat.uniforms.res.value = new THREE.Vector2(window.innerWidth, window.innerHeight)
          // Update isSlide1 based on current slide
          this.mat.uniforms.isSlide1.value = this.data.current === 0 ? 1.0 : 0.0
          // Update crop settings for current slide
          const currentCrop = this.cropSettings[this.data.current] || { horizontal: 0.5, vertical: 0.5 }
          this.mat.uniforms.cropHorizontal.value = currentCrop.horizontal
          this.mat.uniforms.cropVertical.value = currentCrop.vertical
          this.render()
        }
      })
      
      // Continuously render WebGL scene for smooth animation
      const animate = () => {
        requestAnimationFrame(animate)
        if (this.renderer && this.scene && this.camera) {
          this.renderer.render(this.scene, this.camera)
        }
      }
      animate()
    }
  }
  
  // Wait for DOM and libraries to be ready
  function initSlider() {
    // Ensure Three.js and GSAP are loaded (with retry)
    if (typeof THREE === 'undefined' || typeof gsap === 'undefined') {
      // Retry after a short delay if libraries aren't loaded yet
      let retries = 0
      const maxRetries = 20 // Allow up to 2 seconds
      const checkLibraries = setInterval(() => {
        retries++
        if (typeof THREE !== 'undefined' && typeof gsap !== 'undefined') {
          clearInterval(checkLibraries)
          doInit() // Retry initialization
        } else if (retries >= maxRetries) {
          clearInterval(checkLibraries)
          console.error('Libraries failed to load after retries. Three.js:', typeof THREE !== 'undefined', 'GSAP:', typeof gsap !== 'undefined')
        }
      }, 100)
      return null
    }
    
    doInit()
  }
  
  // Actual initialization logic
  function doInit() {
    // Coming Soon Modal
    const comingSoonModal = document.querySelector('.js-coming-soon-modal')
    const modalOverlay = document.querySelector('.js-modal-overlay')
    const modalCloseBtns = document.querySelectorAll('.js-modal-close')
    const notificationForm = document.querySelector('.js-notification-form')
    const emailInput = document.querySelector('.js-email-input')
    const formError = document.querySelector('.js-form-error')
    const formSuccess = document.querySelector('.js-form-success')
    const successMessage = formSuccess?.querySelector('.success-message')
    const submitBtn = document.querySelector('.js-submit-btn')
    const btnText = submitBtn?.querySelector('.btn-text')
    const btnLoader = submitBtn?.querySelector('.btn-loader')
    
    function openModal() {
      if (comingSoonModal) {
        comingSoonModal.classList.add('active')
        document.body.style.overflow = 'hidden'
        // Reset form state
        resetForm()
        // Focus email input
        setTimeout(() => {
          emailInput?.focus()
        }, 300)
      }
    }
    
    function closeModal() {
      if (comingSoonModal) {
        comingSoonModal.classList.remove('active')
        document.body.style.overflow = ''
        // Reset form after close animation
        setTimeout(() => {
          resetForm()
        }, 400)
      }
    }
    
    function resetForm() {
      if (notificationForm) {
        notificationForm.classList.remove('hidden')
        notificationForm.reset()
      }
      if (formError) {
        formError.style.display = 'none'
        formError.textContent = ''
      }
      if (formSuccess) {
        formSuccess.style.display = 'none'
        if (successMessage) successMessage.textContent = ''
      }
      if (emailInput) {
        emailInput.disabled = false
      }
      if (submitBtn) {
        submitBtn.disabled = false
      }
      if (btnText) btnText.style.display = 'inline'
      if (btnLoader) btnLoader.style.display = 'none'
    }
    
    function showError(message) {
      if (formError) {
        formError.textContent = message
        formError.style.display = 'block'
      }
      if (formSuccess) {
        formSuccess.style.display = 'none'
      }
    }
    
    function showSuccess(message) {
      if (formSuccess) {
        if (successMessage) successMessage.textContent = message
        formSuccess.style.display = 'block'
        if (notificationForm) {
          notificationForm.classList.add('hidden')
        }
      }
      if (formError) {
        formError.style.display = 'none'
      }
    }
    
    function setLoading(isLoading) {
      if (emailInput) emailInput.disabled = isLoading
      if (submitBtn) submitBtn.disabled = isLoading
      if (btnText) btnText.style.display = isLoading ? 'none' : 'inline'
      if (btnLoader) btnLoader.style.display = isLoading ? 'inline' : 'none'
    }
    
    // Buy Now button handler (both desktop and mobile)
    const buyNowBtns = document.querySelectorAll('.js-buy-now-btn')
    buyNowBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault()
        openModal()
        // Close mobile menu if open
        const mobileMenu = document.querySelector('.js-mobile-menu')
        const hamburger = document.querySelector('.js-hamburger')
        if (mobileMenu && mobileMenu.classList.contains('active')) {
          mobileMenu.classList.remove('active')
          hamburger?.classList.remove('active')
        }
      })
    })
    
    // Close modal handlers
    if (modalOverlay) {
      modalOverlay.addEventListener('click', closeModal)
    }
    
    modalCloseBtns.forEach(btn => {
      btn.addEventListener('click', closeModal)
    })
    
    // Handle form submission
    if (notificationForm) {
      notificationForm.addEventListener('submit', async (e) => {
        e.preventDefault()
        
        const email = emailInput?.value.trim()
        
        // Validate email
        if (!email) {
          showError('Please enter your email address')
          emailInput?.focus()
          return
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        if (!emailRegex.test(email)) {
          showError('Please enter a valid email address')
          emailInput?.focus()
          return
        }
        
        // Hide previous error
        showError('')
        setLoading(true)
        
        try {
          const response = await fetch('api/notifications.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
          })
          
          const data = await response.json()
          
          if (data.success) {
            showSuccess(data.message || 'Thank you! We\'ll notify you when we launch.')
            
            // Auto close after 3 seconds
            setTimeout(() => {
              closeModal()
            }, 3000)
          } else {
            showError(data.error || 'Something went wrong. Please try again.')
            emailInput?.focus()
          }
        } catch (error) {
          console.error('Notification subscription error:', error)
          showError('Network error. Please check your connection and try again.')
          emailInput?.focus()
        } finally {
          setLoading(false)
        }
      })
    }
    
    // Close modal on ESC key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && comingSoonModal?.classList.contains('active')) {
        closeModal()
      }
    })
    
    // Hamburger menu toggle
    const hamburger = document.querySelector('.js-hamburger')
    const mobileMenu = document.querySelector('.js-mobile-menu')
    
    if (hamburger && mobileMenu) {
      hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active')
        mobileMenu.classList.toggle('active')
        // Prevent body scroll when menu is open
        if (mobileMenu.classList.contains('active')) {
          document.body.style.overflow = 'hidden'
        } else {
          document.body.style.overflow = ''
        }
      })
      
      // Close menu when clicking outside or on menu items
      mobileMenu.addEventListener('click', (e) => {
        if (e.target === mobileMenu) {
          hamburger.classList.remove('active')
          mobileMenu.classList.remove('active')
          document.body.style.overflow = ''
        }
      })
      
      // Close menu on ESC key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
          hamburger.classList.remove('active')
          mobileMenu.classList.remove('active')
          document.body.style.overflow = ''
        }
      })
    }
    
    // Notify button handler is now handled by js-buy-now-btn class above
    
    // Init slider
    if (document.querySelector('.js-slider')) {
      console.log('Initializing slider...')
      const slider = new Slider()
      return slider
    } else {
      console.error('Slider element not found in DOM')
      return null
    }
  }
  
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSlider)
  } else {
    // DOM already ready
    initSlider()
  }