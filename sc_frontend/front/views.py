from django.contrib.auth.decorators import login_required
from django.shortcuts import render_to_response
from django.template import RequestContext
from django.views.decorators.clickjacking import xframe_options_exempt
from front.models import Camera

@xframe_options_exempt
@login_required
def print_cookies(request):
    cameras = Camera.objects.filter(user=request.user)
    context = RequestContext(request, {
        'cameras': cameras,
    })
    return render_to_response('front/index.html', context)
