document.addEventListener('DOMContentLoaded', function () {
  const apiPath = '/api/dashboard';
  const dataPath = '../data/library.json';

  function formatCurrency(n) {
    return '$' + Number(n).toLocaleString();
  }

  function badgeClassForStatus(status) {
    if (!status) return 'secondary';
    const s = status.toLowerCase();
    if (s.includes('active')) return 'success';
    if (s.includes('returned')) return 'info';
    if (s.includes('overdue') || s.includes('due')) return 'danger';
    return 'secondary';
  }

  // Try API first, then fall back to local sample JSON
  fetch(apiPath)
    .then(function (res) {
      if (!res.ok) throw new Error('API not available');
      return res.json();
    })
    .catch(function () {
      return fetch(dataPath).then(function (res) {
        if (!res.ok) throw new Error('Local sample data not available');
        return res.json();
      });
    })
    .then(function (data) {
      // expose loaded data globally for other pages/scripts
      try { window.lmsData = data; } catch (e) { /* ignore */ }
      // dispatch event for any listeners
      try { document.dispatchEvent(new CustomEvent('lms-data-ready', { detail: data })); } catch (e) { /* ignore */ }

      // Stats
      const stats = data.stats || {};
      const totalBooksEl = document.getElementById('total-books');
      const activeMembersEl = document.getElementById('active-members');
      const dueTodayEl = document.getElementById('due-today');
      const pendingFinesEl = document.getElementById('pending-fines');

      if (totalBooksEl) totalBooksEl.textContent = stats.totalBooks ?? '—';
      if (activeMembersEl) activeMembersEl.textContent = stats.activeMembers ?? '—';
      if (dueTodayEl) dueTodayEl.textContent = stats.dueToday ?? '—';
      if (pendingFinesEl) pendingFinesEl.textContent = formatCurrency(stats.pendingFines ?? 0);

      // Transactions
      const txTbody = document.getElementById('transactions-tbody');
      if (txTbody && Array.isArray(data.transactions)) {
        txTbody.innerHTML = '';
        data.transactions.forEach(function (t) {
          const tr = document.createElement('tr');
          const badgeClass = badgeClassForStatus(t.status);
          tr.innerHTML = `
            <td>${t.id || ''}</td>
            <td>${t.member || ''}</td>
            <td>${t.book || ''}</td>
            <td>${t.type || ''}</td>
            <td>${t.date ? new Date(t.date).toLocaleDateString() : ''}</td>
            <td>${t.dueDate ? new Date(t.dueDate).toLocaleDateString() : '-'}</td>
            <td><span class="badge bg-${badgeClass}">${t.status || ''}</span></td>
            <td><button class="btn btn-sm btn-outline-primary">Details</button></td>
          `;
          txTbody.appendChild(tr);
        });
      }

      // Due books
      const dueList = document.getElementById('due-books-list');
      if (dueList && Array.isArray(data.dueBooks)) {
        dueList.innerHTML = '';
        data.dueBooks.forEach(function (b) {
          const a = document.createElement('a');
          a.href = '#';
          a.className = 'list-group-item list-group-item-action';
          a.innerHTML = `
            <div class="d-flex w-100 justify-content-between">
              <h6 class="mb-1">${b.title}</h6>
              <small class="text-danger">${b.label || ''}</small>
            </div>
            <p class="mb-1">Borrowed by: ${b.member}</p>
            <small>Member ID: ${b.memberId}</small>
          `;
          dueList.appendChild(a);
        });
      }

      // Member activities
      const actList = document.getElementById('member-activities-list');
      if (actList && Array.isArray(data.activities)) {
        actList.innerHTML = '';
        data.activities.forEach(function (act) {
          const a = document.createElement('a');
          a.href = '#';
          a.className = 'list-group-item list-group-item-action';
          a.innerHTML = `
            <div class="d-flex w-100 justify-content-between">
              <h6 class="mb-1">${act.title}</h6>
              <small>${act.time}</small>
            </div>
            <p class="mb-1">${act.description}</p>
          `;
          actList.appendChild(a);
        });
      }
    })
    .catch(function (err) {
      console.error('Failed to load data from API or sample file:', err);
    });
});
