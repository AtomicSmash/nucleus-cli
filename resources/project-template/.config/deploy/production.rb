server '{{PRODUCTION_SSH}}', user: '{{PRODUCTION_SSH_USER}}', roles: %w{app db web}, port: {{PRODUCTION_SSH_PORT}}

set :deploy_to, "/www/{{KINSTA_FOLDER}}/public"

set :branch, "{{GIT_DEFAULT_BRANCH}}"

# Confirmation prompt before deployment
puts "\nWARNING: You are about to deploy the `{{GIT_DEFAULT_BRANCH}}` branch to the Production environment!\n\n"
print "Are you sure you want to continue? (yes/no): "
confirmation = $stdin.gets.strip.downcase

unless confirmation == "yes"
  puts "Deployment aborted."
  exit 1
end

puts "\nDeploying `{{GIT_DEFAULT_BRANCH}}` branch to Production environment"
set :linked_dirs, fetch(:linked_dirs, []).push('{{WEB_ROOT}}wp-content/uploads').push('{{WEB_ROOT}}wp-content/wflogs')

set :configFile, "production"

# These variables need to be declared again, that aren't retrievable from above
set :sshPort, {{PRODUCTION_SSH_PORT}}
set :sshUser, "{{PRODUCTION_SSH_USER}}"
set :sshServer, "{{PRODUCTION_SSH}}"

# Deployment tasks
namespace :deploy do
    before :publishing, "deploy:compile_wp_assets"
    before :publishing, "deploy:setup_config"
    before :publishing, "deploy:mu_plugins"
    #after :finished, "deploy:clear_kinsta_cache"
end
