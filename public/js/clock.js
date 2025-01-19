function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    let greeting = '';
    
    // Set greeting based on time
    if (hours >= 5 && hours < 12) {
        greeting = 'Selamat Pagi';
    } else if (hours >= 12 && hours < 15) {
        greeting = 'Selamat Siang';
    } else if (hours >= 15 && hours < 18) {
        greeting = 'Selamat Sore';
    } else {
        greeting = 'Selamat Malam';
    }
    
    // Update greeting
    document.getElementById('greeting').textContent = greeting;
    
    // Update time
    document.getElementById('clock').textContent = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    }) + ' WIB';
    
    // Update date
    document.getElementById('date').textContent = now.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Update every second
setInterval(updateClock, 1000);

// Initial call when document is ready
document.addEventListener('DOMContentLoaded', updateClock);