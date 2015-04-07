from django.conf.urls import patterns, include, url
from django.contrib import admin
from front.views import print_cookies


urlpatterns = patterns('',
    # Examples:
    # url(r'^$', 'sc_frontend.views.home', name='home'),
    # url(r'^blog/', include('blog.urls')),
    url(r'^$', print_cookies),
    url(r'^admin/', include(admin.site.urls)),
)
