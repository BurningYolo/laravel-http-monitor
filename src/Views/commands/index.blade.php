@extends('http-monitor::layout')

@section('title', 'Commands')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Execute Commands</h5>
            </div>
            <div class="card-body">
                
                <!-- Cleanup Command -->
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Cleanup Request Logs</strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Clean up old request logs from the database</p>
                        <form class="command-form" data-command="cleanup">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Days</label>
                                    <input type="number" name="days" class="form-control" value="30" min="1">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-select">
                                        <option value="all">All</option>
                                        <option value="inbound">Inbound</option>
                                        <option value="outbound">Outbound</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status Code (Optional)</label>
                                    <input type="number" name="status" class="form-control" placeholder="e.g. 404">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="dry_run" id="dryRun">
                                            <label class="form-check-label" for="dryRun">Dry Run</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="orphaned_ips" id="orphanedIps">
                                            <label class="form-check-label" for="orphanedIps">Cleanup Orphaned IPs</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Execute Cleanup</button>
                        </form>
                    </div>
                </div>

                <!-- Clear All Command -->
                <div class="card mb-3">
                    <div class="card-header bg-danger text-white">
                        <strong>⚠️ Clear All Logs (Dangerous!)</strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Delete ALL request logs and IP records. This cannot be undone!</p>
                        <form class="command-form" data-command="clear">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">What to Clear</label>
                                    <select name="type" class="form-select">
                                        <option value="all">All</option>
                                        <option value="inbound">Inbound Only</option>
                                        <option value="outbound">Outbound Only</option>
                                        <option value="ips">IPs Only</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-danger mt-3">Clear All Logs</button>
                        </form>
                    </div>
                </div>

                <!-- Prune Command -->
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Prune Based on Config</strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Prune request logs based on retention settings in your config file</p>
                        <form class="command-form" data-command="prune">
                            <button type="submit" class="btn btn-warning">Execute Prune</button>
                        </form>
                    </div>
                </div>

                <!-- Stats Command -->
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Show Statistics</strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Display statistics about tracked requests</p>
                        <form class="command-form" data-command="stats">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Days</label>
                                    <input type="number" name="days" class="form-control" value="7" min="1">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-info mt-3">Show Stats</button>
                        </form>
                    </div>
                </div>

                <!-- Send Stats Command -->
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Send Stats to Webhooks</strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Send request stats to Slack and Discord</p>
                        <form class="command-form" data-command="send-stats">
                            <button type="submit" class="btn btn-success">Send Stats</button>
                        </form>
                    </div>
                </div>

                <!-- Output Section -->
                <div id="commandOutput" class="mt-4" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <strong>Command Output</strong>
                        </div>
                        <div class="card-body">
                            <pre id="outputContent" style="max-height: 400px; overflow-y: auto; font-size: 0.875rem;"></pre>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.command-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const command = this.dataset.command;
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            // Confirmation for dangerous commands
            if (command === 'clear') {
                if (!confirm('⚠️ Are you absolutely sure? This will DELETE ALL selected logs and cannot be undone!')) {
                    return;
                }
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Executing...';
            
            const formData = new FormData(this);
            formData.append('command', command);
            
            try {
                const response = await fetch('{{ route("http-monitor.commands.execute") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const outputDiv = document.getElementById('commandOutput');
                    const outputContent = document.getElementById('outputContent');
                    outputContent.textContent = data.output;
                    outputDiv.style.display = 'block';
                    outputDiv.scrollIntoView({ behavior: 'smooth' });
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error executing command: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    });
});
</script>

@endsection