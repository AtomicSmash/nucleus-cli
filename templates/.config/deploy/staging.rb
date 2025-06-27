server '{{STAGING_SSH}}', user: '{{STAGING_SSH_USER}}', roles: %w{app db web}, port: {{STAGING_SSH_PORT}}

set :deploy_to, "/www/{{KINSTA_FOLDER}}/public"

# Fetch all remote branches starting with "release/" or "hotfix/"
branches = `git branch -r | grep -E 'origin/(release|hotfix)/' | sed 's/origin\\///'`.split("\n")

if branches.empty?
  puts "No release or hotfix branches found!"
  exit 1
end

# Add branch options to the list
puts "\nAvailable remote branches to deploy from:\n\n"
branches.each_with_index { |branch, index| puts "#{index + 1}) #{branch.strip}" }
puts "\nOr enter a custom branch name"

print "\nEnter selection (number or branch name, q to quit): "
selection = $stdin.gets.strip

if selection.downcase == 'q'
  puts "Deployment cancelled."
  exit 0
end

# Try to convert to integer for numbered selection
if selection.match?(/^\d+$/)
  selection = selection.to_i
  if selection < 1 || selection > branches.length
    puts "Invalid selection, please try again."
    exit 1
  end
  selected_branch = branches[selection - 1]
else
  # Treat input as a branch name
  remote_branches = `git branch -r`.split("\n").map { |b| b.strip.gsub('origin/', '') }

  if remote_branches.include?(selection)
    selected_branch = selection
  else
    puts "Error: `#{selection}` does not exist on the remote repository"
    exit 1
  end
end

set :branch, selected_branch
puts "Deploying `#{selected_branch}` branch to Staging (UAT) environment"

set :linked_dirs, fetch(:linked_dirs, []).push('{{WEB_ROOT}}wp-content/uploads').push('{{WEB_ROOT}}wp-content/wflogs')

set :configFile, "staging"

# These variables need to be declared again, that aren't retrievable from above
set :sshPort, {{STAGING_SSH_PORT}}
set :sshUser, "{{STAGING_SSH_USER}}"
set :sshServer, "{{STAGING_SSH}}"

# Deployment tasks
namespace :deploy do
    before :publishing, "deploy:compile_wp_assets"
    before :publishing, "deploy:setup_config"
    before :publishing, "deploy:mu_plugins"
    #after :finished, "deploy:clear_kinsta_cache"
end
