<template>
  <div class="epoch-time-container">
    <div class="epoch-time-box" @click="copyToClipboard" title="Click to copy">
      <span class="epoch-time-text">{{ epochTime }}</span>
      <i :class="showCheckIcon ? 'fas fa-check' : 'fa-solid fa-clipboard'" class="epoch-time-icon"></i>
    </div>
  </div>
</template>

<script>
export default {
  name: 'EpochTimeDisplay',
  data() {
    return {
      epochTime: 0,
      intervalId: null,
      showCheckIcon: false,
      checkIconTimeoutId: null
    }
  },
  mounted() {
    // Set initial epoch time
    this.updateEpochTime();
    
    // Update epoch time every 1 second
    this.intervalId = setInterval(() => {
      this.updateEpochTime();
    }, 1000);
  },
  beforeDestroy() {
    // Clean up intervals and timeouts when component is destroyed
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
    if (this.checkIconTimeoutId) {
      clearTimeout(this.checkIconTimeoutId);
    }
  },
  methods: {
    updateEpochTime() {
      // Get current epoch time in seconds (Unix timestamp)
      this.epochTime = Math.floor(Date.now() / 1000);
    },
    copyToClipboard() {
      // Copy epoch time to clipboard
      const text = this.epochTime.toString();
      
      // Using the modern Clipboard API
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          this.changeIconToCheck();
        }).catch(() => {
          // Fallback if clipboard API fails
          this.fallbackCopyToClipboard(text);
        });
      } else {
        // Fallback for older browsers
        this.fallbackCopyToClipboard(text);
      }
    },
    fallbackCopyToClipboard(text) {
      // Fallback method using textarea
      const textarea = document.createElement('textarea');
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      
      this.changeIconToCheck();
    },
    changeIconToCheck() {
      // Change icon to check for 1 second
      this.showCheckIcon = true;
      
      // Clear existing timeout if any
      if (this.checkIconTimeoutId) {
        clearTimeout(this.checkIconTimeoutId);
      }
      
      // Change back to copy icon after 1 second
      this.checkIconTimeoutId = setTimeout(() => {
        this.showCheckIcon = false;
      }, 1000);
    }
  }
}
</script>

<style scoped>
.epoch-time-container {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

.epoch-time-box {
  background-color: #dc3545;
  color: white;
  padding: 8px 16px;
  border-radius: 4px;
  font-weight: 600;
  font-size: 14px;
  min-width: 140px;
  text-align: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
  font-family: 'Courier New', monospace;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.2s ease;
}

.epoch-time-text {
  display: inline-block;
}

.epoch-time-icon {
  font-size: 16px;
  cursor: pointer;
  transition: all 0.2s ease;
}
</style>
