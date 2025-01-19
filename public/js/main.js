// function updateGreetingAndTime() {
//   const greetingElement = document.getElementById("greeting");
//   const dateTimeElement = document.getElementById("date-time");

//   const now = new Date();
//   const hours = now.getHours();
//   const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
//   const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

// Tentukan salam berdasarkan waktu
let greeting = "Selamat Pagi";
if (hours >= 12 && hours < 18) {
  greeting = "Selamat Siang";
} else if (hours >= 18 && hours < 24) {
  greeting = "Selamat Malam";
}

//   // Format tanggal dan waktu
//   const day = days[now.getDay()];
//   const date = now.getDate();
//   const month = months[now.getMonth()];
//   const year = now.getFullYear();
//   const formattedTime = now.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit", second: "2-digit" });

//   // Perbarui elemen
//   greetingElement.textContent = `${greeting}, Rival`;
//   dateTimeElement.textContent = `${day}, ${date} ${month} ${year} - ${formattedTime}`;
// }

// // // Perbarui setiap detik
// // setInterval(updateGreetingAndTime, 1000);

// // // Panggil segera untuk inisialisasi
// // updateGreetingAndTime();

// // //membuka file
// // // Menangani perubahan file yang dipilih
// // document.getElementById('file-upload').addEventListener('change', function() {
// //   const fileName = this.files[0] ? this.files[0].name : "Tidak ada file yang dipilih";
// //   alert("File yang dipilih: " + fileName);
// // });

//profile

document.addEventListener('DOMContentLoaded', function() {
    const profileToggle = document.getElementById('profileToggle');
    const dropdownMenu = document.getElementById('dropdownMenu');
    
    profileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (dropdownMenu.classList.contains('scale-y-0')) {
            dropdownMenu.classList.remove('scale-y-0', 'opacity-0');
            dropdownMenu.classList.add('scale-y-100', 'opacity-100');
        } else {
            dropdownMenu.classList.remove('scale-y-100', 'opacity-100');
            dropdownMenu.classList.add('scale-y-0', 'opacity-0');
        }
    });

    document.addEventListener('click', function(e) {
        if (!dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('scale-y-100', 'opacity-100');
            dropdownMenu.classList.add('scale-y-0', 'opacity-0');
        }
    });
});

// end profile

// // Modal functionality
// const userModal = document.getElementById("userModal");
// const editModal = document.getElementById("editModal");
// const openModalBtn = document.getElementById("openModal");
// const closeModalBtn = document.getElementById("closeModal");
// const cancelModalBtn = document.getElementById("cancelModal");

// function toggleModal(modal, show) {
//   if (show) {
//     modal.classList.remove("hidden");
//     modal.classList.add("flex");
//   } else {
//     modal.classList.add("hidden");
//     modal.classList.remove("flex");
//   }
// }

// // Wait for DOM to be fully loaded
// document.addEventListener('DOMContentLoaded', function() {
//   // Modal elements
//   const userModal = document.getElementById('userModal');
//   const editModal = document.getElementById('editModal');
//   const openModalBtn = document.getElementById('openModal');
//   const closeModalBtn = document.getElementById('closeModal');
//   const cancelModalBtn = document.getElementById('cancelModal');
//   const profileToggle = document.getElementById('profileToggle');
//   const dropdownMenu = document.getElementById('dropdownMenu');

//   // Only add event listeners if elements exist
//   if (userModal && openModalBtn && closeModalBtn && cancelModalBtn) {
//       // Modal toggle function
//       function toggleModal(modal, show) {
//           if (show) {
//               modal.classList.remove('hidden');
//               modal.classList.add('flex');
//           } else {
//               modal.classList.add('hidden');
//               modal.classList.remove('flex');
//           }
//       }

//       // Add user modal event listeners
//       openModalBtn.addEventListener('click', () => toggleModal(userModal, true));
//       closeModalBtn.addEventListener('click', () => toggleModal(userModal, false));
//       cancelModalBtn.addEventListener('click', () => toggleModal(userModal, false));

//       // Close modal when clicking outside
//       window.addEventListener('click', (e) => {
//           if (e.target === userModal) {
//               toggleModal(userModal, false);
//           }
//           if (e.target === editModal) {
//               toggleModal(editModal, false);
//           }
//       });
//   }

//   // Edit modal function - make it globally available
//   window.openEditModal = function(user) {
//       try {
//           // If user is a string (JSON), parse it
//           if (typeof user === 'string') {
//               user = JSON.parse(user);
//           }

//           document.getElementById('edit_user_id').value = user.id;
//           document.getElementById('edit_username').value = user.username;
//           document.getElementById('edit_email').value = user.email;
//           document.getElementById('edit_role').value = user.role;

//           toggleModal(editModal, true);
//       } catch (error) {
//           console.error('Error in openEditModal:', error);
//       }
//   }

//   window.closeEditModal = function() {
//       toggleModal(editModal, false);
//   }

//   // Profile dropdown functionality
//   if (profileToggle && dropdownMenu) {
//       profileToggle.addEventListener('click', (e) => {
//           e.stopPropagation();
//           dropdownMenu.classList.toggle('scale-y-0');
//           dropdownMenu.classList.toggle('opacity-0');
//       });

//       // Close dropdown when clicking outside
//       document.addEventListener('click', (e) => {
//           if (!profileToggle.contains(e.target)) {
//               dropdownMenu.classList.add('scale-y-0', 'opacity-0');
//           }
//       });
//   }
// });
